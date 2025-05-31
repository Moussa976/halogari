<?php

namespace App\Service;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class PaiementService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        // ✅ Initialise Stripe avec ta clé secrète (dans .env.local)
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    }

    /**
     * Crée un paiement Stripe en mode "autorisé mais pas capturé".
     * Cela bloque les fonds sur la carte du passager sans les débiter.
     * @return string le client_secret du PaymentIntent (à utiliser côté frontend)
     */
    public function autoriserPaiement(Reservation $reservation): string
    {
        // 💰 Calcule le montant total (prix du trajet * nb de places)
        $montant = $reservation->getTrajet()->getPrix() * $reservation->getPlaces();

        // ✅ Création du PaymentIntent
        $intent = PaymentIntent::create([
            'amount' => intval($montant * 100), // Stripe travaille en centimes
            'currency' => 'eur',
            'payment_method_types' => ['card'],
            'capture_method' => 'manual', // 🔥 Très important : différé
            'metadata' => [
                'reservation_id' => $reservation->getId()
            ],
            'receipt_email' => $reservation->getPassager()->getEmail(), // ✅ pour envoyer un reçu
        ]);

        // 💾 Enregistre le paymentIntentId dans la réservation
        $reservation->setPaymentIntentId($intent->id);
        $this->em->flush();

        // 🔁 Retourne le client_secret pour le frontend (Stripe JS/Checkout)
        return $intent->client_secret;
    }


    /**
     * Capture un paiement précédemment autorisé (bloqué).
     *
     * ⚠️ Cette méthode est appelée seulement après confirmation du trajet.
     * Elle déclenche le débit réel de la carte du passager.
     */
    public function capturerPaiement(Reservation $reservation): void
    {
        // 🛑 Vérifie qu'on a bien un paymentIntentId
        if (!$reservation->getPaymentIntentId()) {
            throw new \Exception("Aucun PaymentIntent lié à cette réservation.");
        }

        // 🔁 Récupère le PaymentIntent chez Stripe
        $intent = PaymentIntent::retrieve($reservation->getPaymentIntentId());

        // 💳 Capture le paiement (débit)
        $intent->capture();
    }


    /**
     * Annule un paiement autorisé si le trajet est annulé ou refusé.
     *
     * Cette méthode libère les fonds bloqués sans frais Stripe.
     * Elle ne fonctionne que si le paiement n'a pas encore été capturé.
     */
    public function annulerPaiement(Reservation $reservation): void
    {
        // 🔐 Sécurité : si pas de paymentIntent lié, on sort
        if (!$reservation->getPaymentIntentId()) {
            return;
        }

        // 🧾 Récupération du PaymentIntent chez Stripe
        $intent = PaymentIntent::retrieve($reservation->getPaymentIntentId());

        // ✅ Stripe autorise l'annulation si le paiement n’est pas capturé
        if (in_array($intent->status, ['requires_capture', 'requires_payment_method'])) {
            $intent->cancel();
        }
    }


    /**
     * Rembourse le passager selon la politique d'annulation.
     *
     * - Plus de 24h avant le trajet → 100% remboursé
     * - Entre 24h et 3h avant le trajet → 50% remboursé
     * - Moins de 3h ou no-show → aucun remboursement
     *
     * ⚠️ Cette méthode suppose que le paiement a été autorisé et capturé.
     * Elle utilise l'ID du PaymentIntent pour exécuter un remboursement Stripe.
     *
     * @param Reservation $reservation La réservation à rembourser
     */
    public function rembourserSelonPolitique(Reservation $reservation): void
{
    $intentId = $reservation->getPaymentIntentId();
    if (!$intentId) return;

    $trajet = $reservation->getTrajet();
    $maintenant = new \DateTimeImmutable();

    // ✅ Recomposer une DateTime complète (date + heure)
    $date = $trajet->getDateTrajet();
    $heure = $trajet->getHeureTrajet();
    $dateHeureTrajet = \DateTimeImmutable::createFromFormat(
        'Y-m-d H:i',
        $date->format('Y-m-d') . ' ' . $heure->format('H:i')
    );

    $interval = $dateHeureTrajet->getTimestamp() - $maintenant->getTimestamp();
    $heuresAvant = $interval / 3600;

    // 💰 Montant total payé par le passager
    $montant = $trajet->getPrix() * $reservation->getPlaces();
    $montantCents = intval($montant * 100);

    // 🔁 Calcul du remboursement selon l'heure
    if ($heuresAvant > 24) {
        $remboursement = $montantCents;
    } elseif ($heuresAvant >= 3) {
        $remboursement = intval($montantCents / 2);
    } else {
        $remboursement = 0;
    }

    if ($remboursement > 0) {
        Refund::create([
            'payment_intent' => $intentId,
            'amount' => $remboursement,
        ]);
    }
}


}