<?php

namespace App\Service;

use App\Entity\Commission;
use App\Entity\Paiement;
use App\Entity\Reservation;
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

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!$secretKey) {
            throw new \RuntimeException('STRIPE_SECRET_KEY est manquante.');
        }

        Stripe::setApiKey($secretKey);
    }

    /**
     * Prépare le paiement après acceptation conducteur.
     * Stripe autorise le montant, puis HaloGari le capture plus tard.
     */
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
            'amount' => intval($montant * 100),
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

    /**
     * Point d'entrée admin : capture un paiement autorisé.
     */
    public function capturerPaiement(string $intentId): void
    {
        $intent = PaymentIntent::retrieve($intentId);
        if ($intent->status === 'requires_capture') {
            $intent->capture();
        } elseif ($intent->status !== 'succeeded') {
            throw new \RuntimeException('Ce paiement ne peut pas être capturé dans son état actuel.');
        }

        $paiement = $this->em->getRepository(Paiement::class)->findOneBy([
            'paymentIntentId' => $intentId,
        ]);

        if ($paiement) {
            $paiement->setStatut('capture');
            $paiement->setCapturedAt(new \DateTimeImmutable());
            $reservation = $paiement->getReservation();
            if ($reservation && $reservation->getStatut() !== 'payee') {
                $reservation->setStatut('payee');
            }
            $this->em->flush();
        }
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

            $this->em->flush();

            return !$wasCaptured;
        }

        if ($intent->status === 'requires_capture') {
            $paiement->setStatut('autorise');
            $this->em->flush();
        }

        if (in_array($intent->status, ['canceled', 'requires_payment_method'], true)) {
            $paiement->setStatut($intent->status === 'canceled' ? 'annule' : 'echoue');
            $this->em->flush();
        }

        return false;
    }

    public function verserConducteur(Paiement $paiement): void
    {
        if ($paiement->getStatut() !== 'capture') {
            throw new \RuntimeException('Le paiement doit être capturé avant le versement conducteur.');
        }

        $reservation = $paiement->getReservation();
        if (!$reservation) {
            throw new \RuntimeException('Réservation introuvable pour ce paiement.');
        }

        $this->assertReservationNotAlreadyTransferred($reservation);
        $this->assertTrajetTermineAvantVersement($reservation);

        if (count($reservation->getCommissions()) > 0) {
            throw new \RuntimeException('Ce paiement a déjà été traité pour reversement.');
        }

        $conducteur = $reservation->getTrajet()->getConducteur();
        if (!$conducteur->getStripeAccountId()) {
            throw new \RuntimeException("Ce conducteur n'a pas encore de compte Stripe Connect lié.");
        }

        $repartition = self::calculerRepartition((float) $paiement->getMontant());

        Transfer::create([
            'amount' => intval($repartition['montantConducteur'] * 100),
            'currency' => 'eur',
            'destination' => $conducteur->getStripeAccountId(),
            'metadata' => [
                'reservation_id' => $reservation->getId(),
                'paiement_id' => $paiement->getId(),
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
        $this->em->flush();
    }

    /**
     * @return array{montantBrut: float, commissionHaloGari: float, fraisStripe: float, montantConducteur: float}
     */
    public static function calculerRepartition(float $montantBrut): array
    {
        $commissionHaloGari = max(round($montantBrut * self::COMMISSION_RATE, 2), self::COMMISSION_MINIMUM);
        $fraisStripe = round($montantBrut * self::STRIPE_FEE_RATE + self::STRIPE_FEE_FIXED, 2);
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
            } elseif ($paymentIntent->status === 'succeeded') {
                $this->assertReservationNotAlreadyTransferred($reservation);
                $this->creerRemboursementStripe($intentId);
                $paiement->setStatut('rembourse');
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

        $pourcentage = 0;
        $trajet = $reservation->getTrajet();
        $maintenant = new \DateTimeImmutable();
        $datetimeTrajet = new \DateTimeImmutable(
            $trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i')
        );

        if ($conducteurAnnule) {
            $pourcentage = 100;
        } else {
            $diff = $datetimeTrajet->getTimestamp() - $maintenant->getTimestamp();

            if ($diff > 86400) {
                $pourcentage = 100;
            } elseif ($diff > 10800) {
                $pourcentage = 50;
            }
        }

        if ($pourcentage > 0) {
            $montantTotal = (float) $paiement->getMontant();
            $montantRembourse = round($montantTotal * ($pourcentage / 100), 2);

            $this->creerRemboursementStripe($intentId, intval($montantRembourse * 100));
        }

        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());
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

        $depart = new \DateTimeImmutable(
            $trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i')
        );
        $finEstimee = $depart->modify('+3 hours');

        if ($finEstimee > new \DateTimeImmutable()) {
            throw new \RuntimeException('Le versement conducteur sera disponible après la fin estimée du trajet.');
        }
    }
}
