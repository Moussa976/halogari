<?php

namespace App\Repository;

use App\Entity\Trajet;
use App\Entity\TrajetAlert;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrajetAlert>
 */
class TrajetAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrajetAlert::class);
    }

    public function findActiveDuplicate(User $user, string $depart, string $arrivee, \DateTimeInterface $dateTrajet, int $places): ?TrajetAlert
    {
        $alerts = $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.dateTrajet = :dateTrajet')
            ->andWhere('a.places = :places')
            ->andWhere('a.active = true')
            ->setParameter('user', $user)
            ->setParameter('dateTrajet', $dateTrajet)
            ->setParameter('places', $places)
            ->getQuery()
            ->getResult();

        $departKey = $this->normalizeVillage($depart);
        $arriveeKey = $this->normalizeVillage($arrivee);

        foreach ($alerts as $alert) {
            if (
                $this->normalizeVillage((string) $alert->getDepart()) === $departKey
                && $this->normalizeVillage((string) $alert->getArrivee()) === $arriveeKey
            ) {
                return $alert;
            }
        }

        return null;
    }

    /**
     * @return TrajetAlert[]
     */
    public function findMatchingForTrajet(Trajet $trajet): array
    {
        $alerts = $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->where('a.active = true')
            ->andWhere('a.notifiedAt IS NULL')
            ->andWhere('u.disabledAt IS NULL')
            ->andWhere('a.dateTrajet = :dateTrajet')
            ->andWhere('a.places <= :placesDisponibles')
            ->andWhere('a.user != :conducteur')
            ->setParameter('dateTrajet', $trajet->getDateTrajet())
            ->setParameter('placesDisponibles', $trajet->getPlacesDisponibles())
            ->setParameter('conducteur', $trajet->getConducteur())
            ->getQuery()
            ->getResult();

        $departKey = $this->normalizeVillage((string) $trajet->getDepart());
        $arriveeKey = $this->normalizeVillage((string) $trajet->getArrivee());

        return array_values(array_filter($alerts, function (TrajetAlert $alert) use ($departKey, $arriveeKey): bool {
            return $this->normalizeVillage((string) $alert->getDepart()) === $departKey
                && $this->normalizeVillage((string) $alert->getArrivee()) === $arriveeKey;
        }));
    }

    private function normalizeVillage(string $name): string
    {
        $name = trim(mb_strtolower($name));
        $name = strtr($name, [
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'ÿ' => 'y',
            '’' => "'",
            '`' => "'",
            '´' => "'",
        ]);
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }
}
