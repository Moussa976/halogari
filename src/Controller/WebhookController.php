<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use App\Service\PaiementEventLogger;
use App\Service\SmsService;
use App\Service\StripeConfigService;
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

    public function __construct(NotificationService $notifier, LoggerInterface $logger, StripeConfigService $stripeConfig)
    {
        $this->notifier = $notifier;
        $this->logger = $logger;
        $this->webhookSecret = $stripeConfig->webhookSecret();
    }

    /**
     * @Route("/webhook/stripe", name="stripe_webhook", methods={"POST"})
     */
    public function stripeWebhook(
        Request $request,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $em,
        PaiementEventLogger $eventLogger,
        SmsService $smsService
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
                    $eventLogger->log($paiement, 'paiement_enregistre', 'Paiement enregistré', 'Confirmation reçue depuis Stripe.');
                    $em->flush();
                }
                break;

            case 'payment_intent.succeeded':
                if ($paiement && $paiement->getStatut() !== 'capture') {
                    $paiement->setStatut('capture');
                    $paiement->setCapturedAt(new \DateTimeImmutable());
                    $eventLogger->log($paiement, 'paiement_confirme', 'Paiement confirmé', 'Confirmation reçue depuis Stripe.');
                    $this->notifier->envoyerPaiementCapture($reservation);
                    $smsService->envoyerPlaceConfirmeeAvecCode($reservation);
                }

                if ($reservation->getStatut() !== 'payee') {
                    $reservation->setStatut('payee');
                }

                $em->flush();
                break;

            case 'payment_intent.canceled':
                if ($paiement && !in_array($paiement->getStatut(), ['annule', 'echoue'], true)) {
                    $paiement->setStatut('annule');
                    $eventLogger->log($paiement, 'paiement_annule', 'Paiement annulé', 'Annulation reçue depuis Stripe.');
                    if (in_array($reservation->getStatut(), ['en_attente', 'acceptee', 'payee'], true)) {
                        $reservation->getTrajet()->setPlacesDisponibles(
                            $reservation->getTrajet()->getPlacesDisponibles() + $reservation->getPlaces()
                        );
                    }
                    if ($reservation->getStatut() !== 'annulee' || !$reservation->getCanceledBy()) {
                        $reservation->markCanceled(Reservation::CANCELED_BY_SYSTEME, 'Paiement annulé par Stripe.');
                    }
                    $em->flush();
                    $this->notifier->envoyerEchecPaiement($reservation);
                }
                break;

            case 'charge.refunded':
                if ($paiement && $paiement->getStatut() !== 'rembourse') {
                    $paiement->setStatut('rembourse');
                    $eventLogger->log($paiement, 'remboursement_stripe', 'Remboursement confirmé', 'Remboursement reçu depuis Stripe.');
                    if ($reservation->getStatut() !== 'annulee' || !$reservation->getCanceledBy()) {
                        $reservation->markCanceled(Reservation::CANCELED_BY_SYSTEME, 'Paiement remboursé par Stripe.');
                    }
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
