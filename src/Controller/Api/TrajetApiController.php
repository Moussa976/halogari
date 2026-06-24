<?php

namespace App\Controller\Api;

use App\Application\Trajet\TrajetSearchService;
use App\Entity\Trajet;
use App\Entity\User;
use App\Message\TrajetPublieMessage;
use App\Repository\TrajetRepository;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use App\Service\VillageCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/trajets")
 */
class TrajetApiController extends AbstractController
{
    private TrajetSearchService $trajetSearch;

    public function __construct(TrajetSearchService $trajetSearch)
    {
        $this->trajetSearch = $trajetSearch;
    }

    /**
     * @Route("/rechercher", name="api_trajets_rechercher", methods={"GET"})
     */
    public function rechercher(Request $request, VillageCatalog $villages): JsonResponse
    {
        $depart = (string) $request->query->get('depart', '');
        $arrivee = (string) $request->query->get('arrivee', '');
        $date = (string) $request->query->get('date', '');
        $places = (int) $request->query->get('places', 1);
        $normalizedDate = $this->normalizeDate($date);

        if (!$depart || !$arrivee || !$normalizedDate || $places < 1) {
            return $this->json([
                'message' => 'Paramètres invalides. Utilisez depart, arrivee, date=jj/mm/aaaa et places.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!$villages->isValid($depart) || !$villages->isValid($arrivee)) {
            return $this->json([
                'message' => 'Choisissez le départ et l’arrivée dans la liste des villages.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $trajets = $this->trajetSearch->search(
            $villages->canonicalName($depart),
            $villages->canonicalName($arrivee),
            $normalizedDate,
            $places
        );

        return $this->json([
            'data' => array_map([$this->trajetSearch, 'toMobilePayload'], $trajets),
        ]);
    }

    /**
     * @Route("/populaires", name="api_trajets_populaires", methods={"GET"})
     */
    public function populaires(): JsonResponse
    {
        return $this->json([
            'data' => $this->trajetSearch->popularRoutes(),
        ]);
    }

    /**
     * @Route("/disponibles", name="api_trajets_disponibles", methods={"GET"})
     */
    public function disponibles(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query->get('limit', 10), 30));
        $trajets = $this->trajetSearch->availableUpcoming($limit);

        return $this->json([
            'data' => array_map([$this->trajetSearch, 'toMobilePayload'], $trajets),
        ]);
    }

    /**
     * @Route("/{id}", name="api_trajets_detail", methods={"GET"}, requirements={"id"="\d+"})
     */
    public function detail(int $id, TrajetRepository $trajetRepository): JsonResponse
    {
        $trajet = $trajetRepository->find($id);

        if (!$trajet || $trajet->isAnnule()) {
            return $this->json([
                'message' => 'Trajet introuvable.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => $this->trajetSearch->toMobilePayload($trajet),
        ]);
    }

    /**
     * @Route("", name="api_trajets_create", methods={"POST"})
     */
    public function create(
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        VillageCatalog $villages,
        EntityManagerInterface $em,
        MessageBusInterface $bus
    ): JsonResponse {
        $user = $this->resolveApiUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise pour publier un trajet.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$user->hasPostalAddress()) {
            return $this->json([
                'message' => 'Complétez votre adresse postale avant de publier un trajet.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (!$user->canPublishRide()) {
            return $this->json([
                'message' => 'Pour publier un trajet, votre pièce d’identité et votre RIB doivent être validés.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Corps JSON invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $depart = trim((string) ($payload['depart'] ?? ''));
        $arrivee = trim((string) ($payload['arrivee'] ?? ''));
        $date = $this->normalizeDate((string) ($payload['date'] ?? ''));
        $heure = trim((string) ($payload['heure'] ?? ''));
        $places = (int) ($payload['places'] ?? 0);
        $prix = (float) ($payload['prix'] ?? 0);
        $description = trim((string) ($payload['description'] ?? ''));

        $heureTrajet = \DateTime::createFromFormat('H:i', $heure);
        $dateTrajet = $date ? \DateTime::createFromFormat('Y-m-d', $date) : false;

        if ($depart === '' || $arrivee === '' || $depart === $arrivee) {
            return $this->json(['message' => 'Départ et arrivée invalides.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!$villages->isValid($depart) || !$villages->isValid($arrivee)) {
            return $this->json([
                'message' => 'Choisissez le départ et l’arrivée dans la liste des villages.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $depart = $villages->canonicalName($depart);
        $arrivee = $villages->canonicalName($arrivee);

        if (!$dateTrajet || !$heureTrajet) {
            return $this->json(['message' => 'Date ou heure invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($places < 1 || $places > 8) {
            return $this->json(['message' => 'Indiquez entre 1 et 8 places.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($prix < 1) {
            return $this->json(['message' => 'Le prix doit être au moins de 1 EUR.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($description) < 30) {
            return $this->json(['message' => 'Ajoutez une description d’au moins 30 caractères.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $trajet = new Trajet();
        $trajet->setConducteur($user);
        $trajet->setDepart($depart);
        $trajet->setArrivee($arrivee);
        $trajet->setDateTrajet($dateTrajet);
        $trajet->setHeureTrajet($heureTrajet);
        $trajet->setPlacesDisponibles($places);
        $trajet->setPlaces($places);
        $trajet->setPrix((string) $prix);
        $trajet->setDescription($description);

        $em->persist($trajet);
        $em->flush();

        $bus->dispatch(new TrajetPublieMessage($trajet->getId()));

        return $this->json([
            'message' => 'Trajet publié.',
            'data' => $this->trajetSearch->toMobilePayload($trajet),
        ], JsonResponse::HTTP_CREATED);
    }

    private function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $date);
            if ($parsed && $parsed->format($format) === $date) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private function resolveApiUser(Request $request, UserRepository $userRepository, ApiTokenService $tokenService): ?User
    {
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

    /**
     * @param string[] $types
     */
    private function hasApprovedDocument(User $user, array $types): bool
    {
        foreach ($user->getDocuments() as $document) {
            if (
                in_array(strtolower((string) $document->getTypeDocument()), $types, true)
                && $document->getStatus() === 'approved'
            ) {
                return true;
            }
        }

        return false;
    }
}
