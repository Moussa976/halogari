<?php

namespace App\Controller\Api;

use App\Application\Trajet\TrajetSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    public function rechercher(Request $request): JsonResponse
    {
        $depart = (string) $request->query->get('depart', '');
        $arrivee = (string) $request->query->get('arrivee', '');
        $date = (string) $request->query->get('date', '');
        $places = (int) $request->query->get('places', 1);
        $normalizedDate = $this->normalizeDate($date);

        if (!$depart || !$arrivee || !$normalizedDate || $places < 1) {
            return $this->json([
                'message' => 'Parametres invalides. Utilisez depart, arrivee, date=jj/mm/aaaa et places.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $trajets = $this->trajetSearch->search($depart, $arrivee, $normalizedDate, $places);

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
}
