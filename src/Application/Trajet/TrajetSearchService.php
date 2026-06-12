<?php

namespace App\Application\Trajet;

use App\Entity\Trajet;
use App\Repository\TrajetRepository;

class TrajetSearchService
{
    private TrajetRepository $trajetRepository;

    public function __construct(TrajetRepository $trajetRepository)
    {
        $this->trajetRepository = $trajetRepository;
    }

    /**
     * @return Trajet[]
     */
    public function search(string $depart, string $arrivee, string $date, int $places = 1): array
    {
        $places = max(1, min($places, 8));

        return $this->trajetRepository->findByRecherche(
            trim($depart),
            trim($arrivee),
            $date,
            $places
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function popularRoutes(): array
    {
        return $this->trajetRepository->findPopularTrajets();
    }

    /**
     * @return Trajet[]
     */
    public function availableUpcoming(int $limit = 10): array
    {
        return $this->trajetRepository->findAvailableUpcoming($limit);
    }

    /**
     * Format commun pour Twig leger, API web et future app mobile.
     *
     * @return array<string, mixed>
     */
    public function toMobilePayload(Trajet $trajet): array
    {
        $conducteur = $trajet->getConducteur();

        return [
            'id' => $trajet->getId(),
            'depart' => $trajet->getDepart(),
            'arrivee' => $trajet->getArrivee(),
            'date' => $trajet->getDateTrajet() ? $trajet->getDateTrajet()->format('Y-m-d') : null,
            'heure' => $trajet->getHeureTrajet() ? $trajet->getHeureTrajet()->format('H:i') : null,
            'placesDisponibles' => $trajet->getPlacesDisponibles(),
            'prix' => (float) $trajet->getPrix(),
            'description' => $trajet->getDescription(),
            'conducteur' => [
                'id' => $conducteur ? $conducteur->getId() : null,
                'prenom' => $conducteur ? $conducteur->getPrenom() : null,
                'verifie' => $conducteur ? $conducteur->isProfilVerifieComplet() : false,
                'photo' => $conducteur ? $conducteur->getPhoto() : null,
            ],
        ];
    }
}
