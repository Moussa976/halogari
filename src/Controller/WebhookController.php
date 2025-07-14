<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Stripe\Webhook;

class WebhookController extends AbstractController
{
    private NotificationService $notifier;

    public function __construct(NotificationService $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * @Route("/webhook/stripe", name="stripe_webhook", methods={"POST"})
     */
    public function stripeWebhook(
        Request $request,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $em
    ): Response {
        $log = fn(string $message) => file_put_contents(__DIR__ . '/../../var/log/webhook.log', $message . "\n", FILE_APPEND);

        $log("[1] Webhook Stripe reçu");

        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');
        $secret = $_ENV['STRIPE_WEBHOOK_SECRET'];

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
            $log("[2] Événement validé : {$event->type}");
        } catch (\Throwable $e) {
            $log("[!] Erreur de signature : " . $e->getMessage());
            return new Response('Signature invalide', 400);
        }

        $intent = $event->data->object;
        $intentId = $intent->id;
        $log("[3] PaymentIntent ID : $intentId");

        $reservation = $reservationRepository->findOneByStripeIntentId($intentId);

        if (!$reservation) {
            $log("[!] Aucune réservation trouvée pour cet intent");
            return new Response('Reservation non trouvée', 404);
        }

        $paiement = $reservation->getPaiement();

        switch ($event->type) {
            case 'payment_intent.succeeded':
                if ($paiement && $paiement->getStatut() !== 'capture') {
                    $paiement->setStatut('capture');
                    $this->notifier->envoyerPaiementCapture($reservation);
                    $paiement->setCapturedAt(new \DateTimeImmutable());
                }

                if ($reservation->getStatut() !== 'payee') {
                    $reservation->setStatut('payee');
                    $this->notifier->envoyerConfirmationPaiement($reservation); // ✅ Mail ici
                }

                $em->flush();
                $log("[✔] Paiement capturé, réservation payée, mail envoyé");
                break;

            case 'payment_intent.refunded':
                if ($paiement && $paiement->getStatut() !== 'rembourse') {
                    $paiement->setStatut('rembourse');
                    $paiement->setCapturedAt(new \DateTimeImmutable());
                    $reservation->setStatut('rembourse');
                    $em->flush();

                    $this->notifier->envoyerRemboursementEffectue($reservation);
                    $log("[↩] Paiement remboursé + mail envoyé");
                }
                break;

            case 'payment_intent.canceled':
                if ($paiement && $paiement->getStatut() !== 'echoue') {
                    $paiement->setStatut('echoue');
                    $reservation->setStatut('refusee');
                    $em->flush();

                    $this->notifier->envoyerEchecPaiement($reservation);
                    $log("[✘] Paiement annulé + mail envoyé");
                }
                break;

            default:
                $log("[…] Événement non traité : {$event->type}");
        }

        return new Response('OK', 200);
    }
}
