<?php

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Entity\Paiement;
use App\Entity\Trajet;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use App\Service\NotificationService;
use App\Service\PaiementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/reservations")
 */
class ReservationApiController extends AbstractController
{
    /**
     * @Route("", name="api_reservations_create", methods={"POST"})
     */
    public function create(
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifier,
        UserRepository $userRepository,
        ApiTokenService $tokenService
    ): JsonResponse
    {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json([
                'message' => 'Connexion requise pour reserver ce trajet.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'message' => 'Corps JSON invalide.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $trajetId = (int) ($payload['trajetId'] ?? 0);
        $places = (int) ($payload['places'] ?? 1);

        if ($trajetId < 1 || $places < 1 || $places > 8) {
            return $this->json([
                'message' => 'Parametres invalides.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $trajet = $em->getRepository(Trajet::class)->find($trajetId);
        if (!$trajet || $trajet->isAnnule()) {
            return $this->json([
                'message' => 'Trajet introuvable ou annule.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($trajet->getConducteur() === $user) {
            return $this->json([
                'message' => 'Vous ne pouvez pas reserver votre propre trajet.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $trajetDateTime = new \DateTimeImmutable(
            $trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i')
        );
        if ($trajetDateTime < new \DateTimeImmutable()) {
            return $this->json([
                'message' => 'Ce trajet est deja passe.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $existingReservation = $em->getRepository(Reservation::class)->findOneBy([
            'trajet' => $trajet,
            'passager' => $user,
        ]);
        if ($existingReservation && in_array($existingReservation->getStatut(), ['en_attente', 'acceptee', 'payee'], true)) {
            return $this->json([
                'message' => 'Vous avez deja une reservation active pour ce trajet.',
                'data' => $this->reservationPayload($existingReservation),
            ], JsonResponse::HTTP_CONFLICT);
        }

        if ($places > (int) $trajet->getPlacesDisponibles()) {
            return $this->json([
                'message' => 'Il ne reste pas assez de places disponibles.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $reservation = new Reservation();
        $reservation->setTrajet($trajet);
        $reservation->setPassager($user);
        $reservation->setPlaces($places);
        $reservation->setPrix($trajet->getPrix());
        $reservation->setPrixTotal((string) ((float) $trajet->getPrix() * $places));
        $reservation->setStatut('en_attente');

        $trajet->setPlacesDisponibles((int) $trajet->getPlacesDisponibles() - $places);

        $em->persist($reservation);
        $em->flush();

        $notifier->demanderValidationReservation($reservation);

        return $this->json([
            'message' => 'Demande envoyée. Le conducteur doit répondre.',
            'data' => $this->reservationPayload($reservation),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationPayload(Reservation $reservation): array
    {
        return [
            'id' => $reservation->getId(),
            'trajetId' => $reservation->getTrajet() ? $reservation->getTrajet()->getId() : null,
            'places' => $reservation->getPlaces(),
            'prixTotal' => (float) $reservation->getPrixTotal(),
            'statut' => $reservation->getStatut(),
            'canceledBy' => $reservation->getCanceledBy(),
            'canceledAt' => $reservation->getCanceledAt() ? $reservation->getCanceledAt()->format(\DateTimeInterface::ATOM) : null,
            'cancellationLabel' => $reservation->getCancellationLabel(),
        ];
    }

    /**
     * @Route("/{id}/annuler", name="api_reservations_cancel", methods={"POST"}, requirements={"id"="\d+"})
     */
    public function cancel(
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

        if (!in_array($reservation->getStatut(), ['en_attente', 'acceptee', 'payee'], true)) {
            return $this->json(['message' => 'Cette reservation ne peut plus etre annulee.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $trajet = $reservation->getTrajet();
        $trajetDateTime = new \DateTimeImmutable(
            $trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i')
        );
        if ($trajetDateTime < new \DateTimeImmutable()) {
            return $this->json(['message' => 'Ce trajet est deja passe.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $reservation->markCanceled(Reservation::CANCELED_BY_PASSAGER, 'Annulation demandée par le passager.');

        $paiement = $reservation->getPaiement();
        $placesRestored = false;

        try {
            if ($paiement && $paiement->getStatut() === 'capture') {
                $paiementService->rembourserSelonPolitique($reservation, false);
                $placesRestored = true;
            } elseif ($paiement && $paiement->getStatut() === 'autorise') {
                $paiementService->annulerPaiement($reservation);
                $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());
                $placesRestored = true;
            }
        } catch (\Throwable $exception) {
        }

        if (!$placesRestored) {
            $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());
        }

        $em->flush();

        return $this->json([
            'message' => 'Reservation annulee.',
            'data' => $this->reservationPayload($reservation),
        ]);
    }

    /**
     * @Route("/{id}/accepter", name="api_reservations_accept", methods={"POST"}, requirements={"id"="\d+"})
     */
    public function accept(
        int $id,
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        EntityManagerInterface $em,
        NotificationService $notifier
    ): JsonResponse {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $reservation = $em->getRepository(Reservation::class)->find($id);
        if (!$reservation || $reservation->getTrajet()->getConducteur() !== $user) {
            return $this->json(['message' => 'Reservation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($reservation->getStatut() !== 'en_attente') {
            return $this->json(['message' => 'Cette reservation a deja ete traitee.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $reservation->setStatut('acceptee');

        if (!$reservation->getPaiement()) {
            $paiement = new Paiement();
            $paiement->setMontant((string) $reservation->getPrixTotal());
            $paiement->setStatut('en_attente');
            $paiement->setReservation($reservation);
            $em->persist($paiement);
        }

        $em->flush();
        $notifier->envoyerConfirmationReservation($reservation, 'acceptee');

        return $this->json([
            'message' => 'Reservation acceptee.',
            'data' => $this->reservationPayload($reservation),
        ]);
    }

    /**
     * @Route("/{id}/refuser", name="api_reservations_reject", methods={"POST"}, requirements={"id"="\d+"})
     */
    public function reject(
        int $id,
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        EntityManagerInterface $em,
        NotificationService $notifier
    ): JsonResponse {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $reservation = $em->getRepository(Reservation::class)->find($id);
        if (!$reservation || $reservation->getTrajet()->getConducteur() !== $user) {
            return $this->json(['message' => 'Reservation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($reservation->getStatut() !== 'en_attente') {
            return $this->json(['message' => 'Cette reservation a deja ete traitee.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $trajet = $reservation->getTrajet();
        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());
        $reservation->setStatut('refusee');

        $em->flush();
        $notifier->envoyerConfirmationReservation($reservation, 'refusee');

        return $this->json([
            'message' => 'Reservation refusee.',
            'data' => $this->reservationPayload($reservation),
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
