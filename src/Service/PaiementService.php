<?php

namespace App\Service;

use App\Entity\Commission;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\Trajet;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Transfer;

class PaiementService
{
    private const COMMISSION_RATE = 0.12;
    private const COMMISSION_MINIMUM = 0.50;
    private const STRIPE_FEE_RATE = 0.015;
    private const STRIPE_FEE_FIXED = 0.25;

    private EntityManagerInterface $em;
    private PaiementEventLogger $eventLogger;

    public function __construct(EntityManagerInterface $em, PaiementEventLogger $eventLogger)
    {
        $this->em = $em;
        $this->eventLogger = $eventLogger;

        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!$secretKey) {
            throw new \RuntimeException('STRIPE_SECRET_KEY est manquante.');
        }

        Stripe::setApiKey($secretKey);
    }

    public function autoriserPaiement(Reservation $reservation): string
    {
        $paiement = $reservation->getPaiement();
        $montant = (float) $reservation->getTrajet()->getPrix() * (int) $reservation->getPlaces();

        if (!$paiement) {
            $paiement = new Paiement();
            $paiement->setReservation($reservation);
            $paiement->setStatut('en_attente');
            $this->em->persist($paiement);
            $reservation->setPaiement($paiement);
        }

        if ($paiement->getPaymentIntentId()) {
            $intent = PaymentIntent::retrieve($paiement->getPaymentIntentId());

            if ($intent->status === 'succeeded') {
                $this->synchroniserPaiementStripe($reservation);
                throw new \RuntimeException('Ce paiement est déjà confirmé.');
            }

            if ($intent->status === 'requires_capture') {
                $paiement->setStatut('autorise');
                $this->eventLogger->log($paiement, 'paiement_enregistre', 'Paiement enregistré', 'Le montant est sécurisé pour cette réservation.');
                $this->em->flush();

                throw new \RuntimeException('Ce paiement est déjà enregistré.');
            }

            if ($intent->status === 'canceled') {
                $intent = $this->createPaymentIntent($reservation, $montant);
            }
        } else {
            $intent = $this->createPaymentIntent($reservation, $montant);
        }

        $paiement->setPaymentIntentId($intent->id);
        $paiement->setMontant((string) $montant);
        $paiement->setStatut('en_attente');
        $this->em->flush();

        return $intent->client_secret;
    }

    private function createPaymentIntent(Reservation $reservation, float $montant): PaymentIntent
    {
        $user = $reservation->getPassager();
        $trajet = $reservation->getTrajet();

        return PaymentIntent::create([
            'amount' => (int) round($montant * 100),
            'currency' => 'eur',
            'payment_method_types' => ['card'],
            'capture_method' => 'manual',
            'metadata' => [
                'reservation_id' => $reservation->getId(),
                'trajet' => $trajet->getDepart() . ' -> ' . $trajet->getArrivee(),
                'nom_passager' => $user->getNom() . ' ' . $user->getPrenom(),
                'email_passager' => $user->getEmail(),
            ],
            'receipt_email' => $user->getEmail(),
        ]);
    }

    public function capturerPaiement(string $intentId): void
    {
        $intent = PaymentIntent::retrieve($intentId);
        if ($intent->status === 'requires_capture') {
            $intent->capture();
        } elseif ($intent->status !== 'succeeded') {
            throw new \RuntimeException('Ce paiement ne peut pas être confirmé dans son état actuel.');
        }

        $paiement = $this->em->getRepository(Paiement::class)->findOneBy([
            'paymentIntentId' => $intentId,
        ]);

        if (!$paiement) {
            return;
        }

        $paiement->setStatut('capture');
        $paiement->setCapturedAt(new \DateTimeImmutable());

        $reservation = $paiement->getReservation();
        if ($reservation && $reservation->getStatut() !== 'payee') {
            $reservation->setStatut('payee');
        }

        $this->eventLogger->log($paiement, 'paiement_confirme', 'Paiement confirmé', 'HaloGari a confirmé le paiement.');
        $this->em->flush();
    }

    public function synchroniserPaiementStripe(Reservation $reservation): bool
    {
        $paiement = $reservation->getPaiement();
        if (!$paiement || !$paiement->getPaymentIntentId()) {
            return false;
        }

        $intent = PaymentIntent::retrieve($paiement->getPaymentIntentId());

        if ($intent->status === 'succeeded') {
            $wasCaptured = $paiement->getStatut() === 'capture';
            $paiement->setStatut('capture');

            if (!$paiement->getCapturedAt()) {
                $paiement->setCapturedAt(new \DateTimeImmutable());
            }

            if ($reservation->getStatut() !== 'payee') {
                $reservation->setStatut('payee');
            }

            if (!$wasCaptured) {
                $this->eventLogger->log($paiement, 'paiement_confirme', 'Paiement confirmé', 'Statut confirmé par Stripe.');
            }

            $this->em->flush();

            return !$wasCaptured;
        }

        if ($intent->status === 'requires_capture') {
            $paiement->setStatut('autorise');
            $this->eventLogger->log($paiement, 'paiement_enregistre', 'Paiement enregistré', 'Statut enregistré par Stripe.');
            $this->em->flush();
        }

        if (in_array($intent->status, ['canceled', 'requires_payment_method'], true)) {
            $paiement->setStatut($intent->status === 'canceled' ? 'annule' : 'echoue');
            $this->eventLogger->log($paiement, 'paiement_echec', 'Paiement non abouti', 'Stripe a signalé un paiement annulé ou expiré.');
            $this->em->flush();
        }

        return false;
    }

    public function verserConducteur(Paiement $paiement): void
    {
        if ($paiement->getStatut() !== 'capture') {
            throw new \RuntimeException('Le paiement doit être confirmé avant le versement conducteur.');
        }

        $reservation = $paiement->getReservation();
        if (!$reservation) {
            throw new \RuntimeException('Réservation introuvable pour ce paiement.');
        }

        $this->assertReservationNotAlreadyTransferred($reservation);
        $this->assertTrajetTermineAvantVersement($reservation);

        if ($reservation->getCommissions()->count() > 0) {
            throw new \RuntimeException('Ce paiement a déjà été traité pour reversement.');
        }

        $conducteur = $reservation->getTrajet()->getConducteur();
        if (!$conducteur->getStripeAccountId()) {
            throw new \RuntimeException("Ce conducteur n'a pas encore de compte Stripe Connect lié.");
        }

        $montantDisponible = $paiement->getMontantDisponible();
        if ($montantDisponible <= 0) {
            throw new \RuntimeException('Aucun montant disponible pour le versement conducteur.');
        }

        $repartition = self::calculerRepartition($montantDisponible, (float) $paiement->getMontant());

        Transfer::create([
            'amount' => (int) round($repartition['montantConducteur'] * 100),
            'currency' => 'eur',
            'destination' => $conducteur->getStripeAccountId(),
            'metadata' => [
                'reservation_id' => $reservation->getId(),
                'paiement_id' => $paiement->getId(),
                'montant_disponible' => $montantDisponible,
            ],
        ]);

        $commission = new Commission();
        $commission->setReservation($reservation);
        $commission->setMontantBrut((string) $repartition['montantBrut']);
        $commission->setFraisStripe((string) $repartition['fraisStripe']);
        $commission->setCommissionHaloGari((string) $repartition['commissionHaloGari']);
        $commission->setMontantConducteur((string) $repartition['montantConducteur']);
        $commission->setMontantNet((string) $repartition['commissionHaloGari']);

        $this->em->persist($commission);
        $this->eventLogger->log($paiement, 'versement_conducteur', 'Versement conducteur', 'La part conducteur a été envoyée.', null, $repartition);
        $this->em->flush();
    }

    /**
     * @return array{montantBrut: float, commissionHaloGari: float, fraisStripe: float, montantConducteur: float}
     */
    public static function calculerRepartition(float $montantBrut, ?float $montantFraisStripe = null): array
    {
        $commissionHaloGari = max(round($montantBrut * self::COMMISSION_RATE, 2), self::COMMISSION_MINIMUM);
        $baseFraisStripe = $montantFraisStripe ?? $montantBrut;
        $fraisStripe = round($baseFraisStripe * self::STRIPE_FEE_RATE + self::STRIPE_FEE_FIXED, 2);
        $montantConducteur = max(round($montantBrut - $commissionHaloGari - $fraisStripe, 2), 0);

        return [
            'montantBrut' => round($montantBrut, 2),
            'commissionHaloGari' => $commissionHaloGari,
            'fraisStripe' => $fraisStripe,
            'montantConducteur' => $montantConducteur,
        ];
    }

    public function annulerPaiement(Reservation $reservation): void
    {
        $paiement = $reservation->getPaiement();

        if (!$paiement || !$paiement->getPaymentIntentId()) {
            return;
        }

        $intentId = $paiement->getPaymentIntentId();

        try {
            $paymentIntent = PaymentIntent::retrieve($intentId);

            if ($paymentIntent->status === 'requires_capture') {
                $paymentIntent->cancel();
                $paiement->setStatut('annule');
                $this->eventLogger->log($paiement, 'paiement_annule', 'Paiement annulé', 'Le paiement enregistré a été annulé avant confirmation.');
            } elseif ($paymentIntent->status === 'succeeded') {
                $this->assertReservationNotAlreadyTransferred($reservation);
                $montantRembourse = (float) $paiement->getMontant();
                $this->creerRemboursementStripe($intentId);
                $paiement->setStatut('rembourse');
                $paiement->setMontantRembourse(number_format($montantRembourse, 2, '.', ''));
                $this->eventLogger->log($paiement, 'remboursement_total', 'Remboursement total', 'Le paiement confirmé a été remboursé.', null, [
                    'montant' => $montantRembourse,
                    'pourcentage' => 100,
                ]);
            }

            $this->em->flush();
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Le paiement n'a pas pu être annulé ou remboursé : " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function rembourserPaiement(string $intentId): void
    {
        $this->creerRemboursementStripe($intentId);

        $paiement = $this->em->getRepository(Paiement::class)->findOneBy(['paymentIntentId' => $intentId]);
        if ($paiement) {
            $montantRembourse = (float) $paiement->getMontant();
            $paiement->setMontantRembourse(number_format($montantRembourse, 2, '.', ''));
            $this->eventLogger->log($paiement, 'remboursement_total', 'Remboursement total', 'Remboursement demandé depuis l’administration.', null, [
                'montant' => $montantRembourse,
                'pourcentage' => 100,
            ]);
        }
    }

    public function rembourserPaiementSelonPourcentage(Paiement $paiement, int $pourcentage): void
    {
        if ($pourcentage <= 0 || $pourcentage > 100) {
            throw new \RuntimeException('Le pourcentage de remboursement doit être compris entre 1 et 100.');
        }

        $reservation = $paiement->getReservation();
        if ($reservation) {
            $this->assertReservationNotAlreadyTransferred($reservation);
        }

        $intentId = $paiement->getPaymentIntentId();
        if (!$intentId) {
            throw new \RuntimeException('PaymentIntent introuvable pour ce paiement.');
        }

        $montantTotal = (float) $paiement->getMontant();
        $montantRembourse = round($montantTotal * ($pourcentage / 100), 2);
        $amount = $pourcentage >= 100 ? null : (int) round($montantRembourse * 100);

        $this->creerRemboursementStripe($intentId, $amount);

        $paiement->addMontantRembourse($montantRembourse);
        $paiement->setStatut($pourcentage >= 100 ? 'rembourse' : 'rembourse_partiel');
        $this->eventLogger->log(
            $paiement,
            $pourcentage >= 100 ? 'remboursement_total' : 'remboursement_partiel',
            $pourcentage >= 100 ? 'Remboursement total' : 'Remboursement partiel',
            sprintf('Remboursement de %d %% appliqué depuis l’administration.', $pourcentage),
            null,
            ['pourcentage' => $pourcentage, 'montant' => $montantRembourse]
        );

        $this->em->flush();
    }

    public function rembourserSelonPolitique(Reservation $reservation, bool $conducteurAnnule = false): void
    {
        $paiement = $reservation->getPaiement();
        if (!$paiement) {
            return;
        }

        $intentId = $paiement->getPaymentIntentId();

        if (!$intentId || $paiement->getStatut() !== 'capture') {
            return;
        }

        $this->assertReservationNotAlreadyTransferred($reservation);

        $trajet = $reservation->getTrajet();
        $maintenant = new \DateTimeImmutable();
        $datetimeTrajet = new \DateTimeImmutable(
            $trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i')
        );
        $pourcentage = self::calculerPourcentageRemboursement($datetimeTrajet, $maintenant, $conducteurAnnule);

        if ($pourcentage > 0) {
            $montantTotal = (float) $paiement->getMontant();
            $montantRembourse = round($montantTotal * ($pourcentage / 100), 2);

            $this->creerRemboursementStripe($intentId, (int) round($montantRembourse * 100));
            $paiement->addMontantRembourse($montantRembourse);
            $paiement->setStatut($pourcentage >= 100 ? 'rembourse' : 'rembourse_partiel');
            $this->eventLogger->log($paiement, 'remboursement_politique', 'Remboursement selon la politique HaloGari', sprintf('Remboursement de %d %% appliqué.', $pourcentage), null, [
                'pourcentage' => $pourcentage,
                'montant' => $montantRembourse,
                'conducteurAnnule' => $conducteurAnnule,
            ]);
        } else {
            $this->eventLogger->log($paiement, 'annulation_sans_remboursement', 'Annulation sans remboursement automatique', 'La demande est à contrôler si nécessaire.');
        }

        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());
    }

    public static function calculerPourcentageRemboursement(
        \DateTimeInterface $departTrajet,
        \DateTimeInterface $maintenant,
        bool $conducteurAnnule = false
    ): int {
        if ($conducteurAnnule) {
            return 100;
        }

        $diff = $departTrajet->getTimestamp() - $maintenant->getTimestamp();

        if ($diff > 86400) {
            return 100;
        }

        if ($diff > 10800) {
            return 50;
        }

        return 0;
    }

    public static function calculerPourcentageRemboursementReservation(Reservation $reservation): int
    {
        if ($reservation->getCanceledBy() === Reservation::CANCELED_BY_CONDUCTEUR) {
            return 100;
        }

        if ($reservation->getCanceledBy() !== Reservation::CANCELED_BY_PASSAGER || !$reservation->getCanceledAt()) {
            return 0;
        }

        $trajet = $reservation->getTrajet();
        if (!$trajet || !$trajet->getDateTrajet() || !$trajet->getHeureTrajet()) {
            return 0;
        }

        $departTrajet = new \DateTimeImmutable(
            $trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i')
        );

        return self::calculerPourcentageRemboursement($departTrajet, $reservation->getCanceledAt());
    }

    private function assertReservationNotAlreadyTransferred(Reservation $reservation): void
    {
        if ($reservation->getCommissions()->count() > 0) {
            throw new \RuntimeException('Le versement conducteur a déjà été effectué. Le remboursement doit être traité manuellement depuis Stripe et l’administration HaloGari.');
        }
    }

    private function creerRemboursementStripe(string $intentId, ?int $amount = null): void
    {
        $payload = ['payment_intent' => $intentId];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        try {
            Refund::create($payload);
        } catch (InvalidRequestException $exception) {
            if (str_contains($exception->getMessage(), 'No such payment_intent')) {
                throw new \RuntimeException(
                    "Stripe ne retrouve pas ce paiement avec la clé configurée actuellement. Il a probablement été créé avec une ancienne clé Stripe : remboursez-le depuis l'ancien compte Stripe ou traitez ce paiement de test manuellement.",
                    0,
                    $exception
                );
            }

            throw $exception;
        }
    }

    private function assertTrajetTermineAvantVersement(Reservation $reservation): void
    {
        $trajet = $reservation->getTrajet();
        if (!$trajet || !$trajet->getDateTrajet() || !$trajet->getHeureTrajet()) {
            throw new \RuntimeException('Impossible de vérifier la fin du trajet avant le versement conducteur.');
        }

        if ($trajet->getStatutOperationnel() === Trajet::SUIVI_LITIGE) {
            throw new \RuntimeException('Ce trajet est en litige : le versement conducteur est bloqué.');
        }

        if (!$trajet->isPretPourVersement()) {
            throw new \RuntimeException('Le versement conducteur sera disponible après la fin du trajet ou après validation admin.');
        }
    }
}
