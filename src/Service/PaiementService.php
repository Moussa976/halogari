<?php

namespace App\Service;

use App\Entity\Commission;
use App\Entity\Reservation;
use App\Entity\Paiement;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Refund;

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
     * Autorise un paiement Stripe (sans le capturer immédiatement).
     */
    public function autoriserPaiement(Reservation $reservation): string
    {
        $paiement = $reservation->getPaiement();
        $montant = $reservation->getTrajet()->getPrix() * $reservation->getPlaces();

        $destination = $reservation->getTrajet()->getConducteur()->getStripeAccountId();

        if (!$destination) {
            throw new \Exception("Ce conducteur n’a pas encore de compte Stripe Connect lié.");
        }

        if (!$paiement) {
            $paiement = new Paiement();
            $paiement->setReservation($reservation);
            $paiement->setStatut('en_attente');
            $this->em->persist($paiement);
            $reservation->setPaiement($paiement);
        }

        // Réutilisation si possible
        if ($paiement->getPaymentIntentId()) {
            $intent = PaymentIntent::retrieve($paiement->getPaymentIntentId());
            if (in_array($intent->status, ['canceled', 'succeeded'])) {
                $intent = $this->createPaymentIntent($reservation, $montant);
            }
        } else {
            $intent = $this->createPaymentIntent($reservation, $montant);
        }

        $paiement->setPaymentIntentId($intent->id);
        $paiement->setMontant($montant);
        $paiement->setStatut('autorise');
        $this->em->flush();

        return $intent->client_secret;
    }

    /**
     * Création d’un PaymentIntent avec manual capture + transfert vers le conducteur.
     */
    private function createPaymentIntent(Reservation $reservation, float $montant): PaymentIntent
    {
        $user = $reservation->getPassager();
        $trajet = $reservation->getTrajet();
        $conducteur = $trajet->getConducteur();

        // 💸 Calcul de la commission HaloGari : 12% ou minimum 0,50€
        $commission = max(round($montant * 0.12, 2), 0.50);

        return PaymentIntent::create([
            'amount' => intval($montant * 100),
            'currency' => 'eur',
            'payment_method_types' => ['card'],
            'capture_method' => 'manual',
            'application_fee_amount' => intval($commission * 100), // 🔧 modifie ici si tu veux une commission
            'transfer_data' => [
                'destination' => $conducteur->getStripeAccountId(),
            ],
            'metadata' => [
                'reservation_id' => $reservation->getId(),
                'trajet' => $trajet->getDepart() . ' → ' . $trajet->getArrivee(),
                'nom_passager' => $user->getNom() . ' ' . $user->getPrenom(),
                'email_passager' => $user->getEmail(),
            ],
            'receipt_email' => $user->getEmail(),
        ]);
    }

    /**
     * Capture d’un paiement après autorisation.
     */
    public function capturerPaiement(string $intentId): void
    {
        $intent = PaymentIntent::retrieve($intentId);
        $intent->capture();

        $paiement = $this->em->getRepository(Paiement::class)->findOneBy(['paymentIntentId' => $intentId]);

        if ($paiement) {
            $paiement->setStatut('capture');

            $reservation = $paiement->getReservation();
            $montantBrut = $paiement->getMontant(); // ex: 22 €
            $commissionHaloGari = max(round($montantBrut * 0.12, 2), 0.50); // 12% ou 0.50€
            $fraisStripe = round($montantBrut * 0.014 + 0.25, 2); // Stripe : 1.4% + 0.25 €
            $montantNet = $commissionHaloGari - $fraisStripe;

            $commission = new Commission();
            $commission->setReservation($reservation);
            $commission->setMontantBrut($montantBrut);
            $commission->setFraisStripe($fraisStripe);
            $commission->setMontantNet($montantNet); // Ce que tu gagnes réellement

            $this->em->persist($commission);
            $this->em->flush();
        }
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
                $paiement->setStatut('annule'); // ou 'echoue'
            } elseif ($paymentIntent->status === 'succeeded') {
                \Stripe\Refund::create([
                    'payment_intent' => $intentId,
                ]);
                $paiement->setStatut('rembourse');
            }

            $this->em->flush();

        } catch (\Exception $e) {
            // Log optionnel
        }
    }


    /**
     * Rembourse un paiement déjà capturé.
     */
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
            return; // Aucun remboursement possible
        }

        $pourcentage = 0;
        $trajet = $reservation->getTrajet();
        $maintenant = new \DateTimeImmutable();
        $datetimeTrajet = (new \DateTimeImmutable($trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i')));

        if ($conducteurAnnule) {
            $pourcentage = 100;
        } else {
            $diff = $datetimeTrajet->getTimestamp() - $maintenant->getTimestamp();

            if ($diff > 86400) { // > 24h
                $pourcentage = 100;
            } elseif ($diff > 10800) { // > 3h
                $pourcentage = 50;
            } else {
                $pourcentage = 0;
            }
        }

        if ($pourcentage > 0) {
            $montantTotal = $paiement->getMontant(); // en euros
            $montantRembourse = round($montantTotal * ($pourcentage / 100), 2); // en euros

            \Stripe\Refund::create([
                'payment_intent' => $intentId,
                'amount' => intval($montantRembourse * 100),
            ]);
        }

        // ✅ On libère les places réservées
        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());
    }

}
