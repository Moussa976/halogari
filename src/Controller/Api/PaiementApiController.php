<?php

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use App\Service\NotificationService;
use App\Service\PaiementService;
use App\Service\SmsService;
use App\Service\StripeConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/paiements")
 */
class PaiementApiController extends AbstractController
{
    /**
     * @Route("/reservations/{id}/intent", name="api_payment_intent", methods={"POST"}, requirements={"id"="\d+"})
     */
    public function intent(
        int $id,
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        EntityManagerInterface $em,
        PaiementService $paiementService,
        NotificationService $notificationService,
        SmsService $smsService,
        StripeConfigService $stripeConfig
    ): JsonResponse {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $reservation = $em->getRepository(Reservation::class)->find($id);
        if (!$reservation || $reservation->getPassager() !== $user) {
            return $this->json(['message' => 'Réservation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        try {
            if ($paiementService->synchroniserPaiementStripe($reservation)) {
                $notificationService->envoyerPaiementCapture($reservation);
                $smsService->envoyerPlaceConfirmeeAvecCode($reservation);
            }
        } catch (\Throwable $error) {
            // Le webhook Stripe ou la confirmation web reprendront la synchronisation.
        }

        if ($reservation->getPaiement() && $reservation->getPaiement()->getStatut() === 'capture') {
            return $this->json([
                'message' => 'Paiement déjà confirmé.',
                'status' => 'capture',
                'paymentUrl' => $this->generateUrl('paiement_confirmation', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }

        if ($reservation->getPaiement() && $reservation->getPaiement()->getStatut() === 'autorise') {
            return $this->json([
                'message' => 'Paiement déjà enregistré.',
                'status' => 'autorise',
                'paymentUrl' => $this->generateUrl('paiement_confirmation', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }

        if ($reservation->getStatut() !== 'acceptee') {
            return $this->json(['message' => 'Le paiement sera disponible après acceptation du conducteur.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $clientSecret = $paiementService->autoriserPaiement($reservation);
        } catch (\Throwable $error) {
            return $this->json([
                'message' => $error->getMessage(),
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Enregistrement du paiement initialisé.',
            'clientSecret' => $clientSecret,
            'stripePublicKey' => $stripeConfig->publicKey(),
            'amount' => (float) $reservation->getPrixTotal(),
            'paymentUrl' => $this->generateUrl('paiement_form', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    private function resolveUser(Request $request, UserRepository $userRepository, ApiTokenService $tokenService): ?User
    {
        $sessionUser = $this->getUser();
        if ($sessionUser instanceof User) {
            return $sessionUser;
        }

        $authorization = (string) $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }

        $payload = $tokenService->parse($matches[1]);
        if (!$payload) {
            return null;
        }

        $user = $userRepository->find($payload['uid']);
        if ($user instanceof User && $user->isDisabled()) {
            return null;
        }

        return $user;
    }
}
