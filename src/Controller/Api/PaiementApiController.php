<?php

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use App\Service\PaiementService;
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
        PaiementService $paiementService
    ): JsonResponse {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $reservation = $em->getRepository(Reservation::class)->find($id);
        if (!$reservation || $reservation->getPassager() !== $user) {
            return $this->json(['message' => 'Reservation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($reservation->getStatut() !== 'acceptee') {
            return $this->json(['message' => 'Le paiement sera disponible apres acceptation du conducteur.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $clientSecret = $paiementService->autoriserPaiement($reservation);
        } catch (\Throwable $error) {
            return $this->json([
                'message' => $error->getMessage(),
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Paiement initialise.',
            'clientSecret' => $clientSecret,
            'stripePublicKey' => (string) ($_ENV['STRIPE_PUBLIC_KEY'] ?? ''),
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

        return $userRepository->find($payload['uid']);
    }
}
