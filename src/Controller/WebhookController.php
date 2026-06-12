<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController extends AbstractController
{
    private NotificationService $notifier;
    private LoggerInterface $logger;
    private string $webhookSecret;

    public function __construct(NotificationService $notifier, LoggerInterface $logger, string $stripeWebhookSecret)
    {
        $this->notifier = $notifier;
        $this->logger = $logger;
        $this->webhookSecret = $stripeWebhookSecret;
    }

    /**
     * @Route("/webhook/stripe", name="stripe_webhook", methods={"POST"})
     */
    public function stripeWebhook(
        Request $request,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $em
    ): Response {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        if (!$sigHeader || !$this->webhookSecret) {
            $this->logger->warning('Webhook Stripe rejeté : signature ou secret manquant.');
            return new Response('Signature manquante', Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (\Throwable $exception) {
            $this->logger->warning('Webhook Stripe rejeté : signature invalide.', [
                'exception' => $exception->getMessage(),
            ]);

            return new Response('Signature invalide', Response::HTTP_BAD_REQUEST);
        }

        $stripeObject = $event->data->object ?? null;
        $intentId = $stripeObject->id ?? null;

        if ($event->type === 'charge.refunded') {
            $intentId = $stripeObject->payment_intent ?? null;
        }

        if (!$intentId) {
            $this->logger->warning('Webhook Stripe sans PaymentIntent.', [
                'type' => $event->type,
            ]);

            return new Response('PaymentIntent manquant', Response::HTTP_BAD_REQUEST);
        }

        $reservation = $reservationRepository->findOneByStripeIntentId($intentId);

        if (!$reservation) {
            $this->logger->info('Webhook Stripe ignoré : réservation introuvable.', [
                'type' => $event->type,
                'paymentIntentId' => $intentId,
            ]);

            return new Response('OK', Response::HTTP_OK);
        }

        $paiement = $reservation->getPaiement();

        switch ($event->type) {
            case 'payment_intent.amount_capturable_updated':
                if ($paiement && $paiement->getStatut() !== 'autorise') {
                    $paiement->setStatut('autorise');
                    $em->flush();
                }
                break;

            case 'payment_intent.succeeded':
                if ($paiement && $paiement->getStatut() !== 'capture') {
                    $paiement->setStatut('capture');
                    $paiement->setCapturedAt(new \DateTimeImmutable());
                    $this->notifier->envoyerPaiementCapture($reservation);
                }

                if ($reservation->getStatut() !== 'payee') {
                    $reservation->setStatut('payee');
                }

                $em->flush();
                break;

            case 'payment_intent.canceled':
                if ($paiement && !in_array($paiement->getStatut(), ['annule', 'echoue'], true)) {
                    $paiement->setStatut('annule');
                    if (in_array($reservation->getStatut(), ['en_attente', 'acceptee', 'payee'], true)) {
                        $reservation->getTrajet()->setPlacesDisponibles(
                            $reservation->getTrajet()->getPlacesDisponibles() + $reservation->getPlaces()
                        );
                    }
                    $reservation->setStatut('annulee');
                    $em->flush();
                    $this->notifier->envoyerEchecPaiement($reservation);
                }
                break;

            case 'charge.refunded':
                if ($paiement && $paiement->getStatut() !== 'rembourse') {
                    $paiement->setStatut('rembourse');
                    $reservation->setStatut('annulee');
                    $em->flush();
                    $this->notifier->envoyerRemboursementEffectue($reservation);
                }
                break;

            default:
                $this->logger->info('Webhook Stripe reçu sans action côté HaloGari.', [
                    'type' => $event->type,
                    'paymentIntentId' => $intentId,
                ]);
        }

        return new Response('OK', Response::HTTP_OK);
    }
}
