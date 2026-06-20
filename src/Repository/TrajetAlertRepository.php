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
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('LOWER(a.depart) = LOWER(:depart)')
            ->andWhere('LOWER(a.arrivee) = LOWER(:arrivee)')
            ->andWhere('a.dateTrajet = :dateTrajet')
            ->andWhere('a.places = :places')
            ->andWhere('a.active = true')
            ->setParameter('user', $user)
            ->setParameter('depart', $depart)
            ->setParameter('arrivee', $arrivee)
            ->setParameter('dateTrajet', $dateTrajet)
            ->setParameter('places', $places)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return TrajetAlert[]
     */
    public function findMatchingForTrajet(Trajet $trajet): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->where('a.active = true')
            ->andWhere('a.notifiedAt IS NULL')
            ->andWhere('u.disabledAt IS NULL')
            ->andWhere('LOWER(a.depart) = LOWER(:depart)')
            ->andWhere('LOWER(a.arrivee) = LOWER(:arrivee)')
            ->andWhere('a.dateTrajet = :dateTrajet')
            ->andWhere('a.places <= :placesDisponibles')
            ->andWhere('a.user != :conducteur')
            ->setParameter('depart', $trajet->getDepart())
            ->setParameter('arrivee', $trajet->getArrivee())
            ->setParameter('dateTrajet', $trajet->getDateTrajet())
            ->setParameter('placesDisponibles', $trajet->getPlacesDisponibles())
            ->setParameter('conducteur', $trajet->getConducteur())
            ->getQuery()
            ->getResult();
    }
}
