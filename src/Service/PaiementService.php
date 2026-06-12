<?php

namespace App\Service;

use App\Entity\Commission;
use App\Entity\Paiement;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Transfer;

class PaiementService
{
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
     * Stripe capture le montant quand le passager confirme sa carte.
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
            if (in_array($intent->status, ['canceled', 'succeeded'], true)) {
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
            'capture_method' => 'automatic',
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
     * Ancien point d'entrée admin conservé pour les anciens paiements en capture manuelle.
     */
    public function capturerPaiement(string $intentId): void
    {
        $intent = PaymentIntent::retrieve($intentId);
        if ($intent->status === 'requires_capture') {
            $intent->capture();
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

    public function verserConducteur(Paiement $paiement): void
    {
        if ($paiement->getStatut() !== 'capture') {
            throw new \RuntimeException('Le paiement doit être capturé avant le versement conducteur.');
        }

        $reservation = $paiement->getReservation();
        if (!$reservation) {
            throw new \RuntimeException('Réservation introuvable pour ce paiement.');
        }

        if (count($reservation->getCommissions()) > 0) {
            throw new \RuntimeException('Ce paiement a déjà été traité pour reversement.');
        }

        $conducteur = $reservation->getTrajet()->getConducteur();
        if (!$conducteur->getStripeAccountId()) {
            throw new \RuntimeException("Ce conducteur n'a pas encore de compte Stripe Connect lié.");
        }

        $montantBrut = (float) $paiement->getMontant();
        $commissionHaloGari = max(round($montantBrut * 0.12, 2), 0.50);
        $fraisStripe = round($montantBrut * 0.014 + 0.25, 2);
        $montantConducteur = max(round($montantBrut - $commissionHaloGari, 2), 0);
        $commissionNette = round($commissionHaloGari - $fraisStripe, 2);

        Transfer::create([
            'amount' => intval($montantConducteur * 100),
            'currency' => 'eur',
            'destination' => $conducteur->getStripeAccountId(),
            'metadata' => [
                'reservation_id' => $reservation->getId(),
                'paiement_id' => $paiement->getId(),
            ],
        ]);

        $commission = new Commission();
        $commission->setReservation($reservation);
        $commission->setMontantBrut((string) $montantBrut);
        $commission->setFraisStripe((string) $fraisStripe);
        $commission->setMontantNet((string) $commissionNette);

        $this->em->persist($commission);
        $this->em->flush();
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
                Refund::create([
                    'payment_intent' => $intentId,
                ]);
                $paiement->setStatut('rembourse');
            }

            $this->em->flush();
        } catch (\Exception $e) {
            // Le webhook Stripe ou l'admin peut reprendre le traitement ensuite.
        }
    }

    public function rembourserPaiement(string $intentId): void
    {
        Refund::create(['payment_intent' => $intentId]);
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

            Refund::create([
                'payment_intent' => $intentId,
                'amount' => intval($montantRembourse * 100),
            ]);
        }

        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());
    }
}
