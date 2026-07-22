<?php

namespace App\Repository;

use App\Entity\VisitorDailyVisit;
use App\Entity\VisitorProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VisitorDailyVisit>
 */
class VisitorDailyVisitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VisitorDailyVisit::class);
    }

    public function findOneForVisitorAndDay(VisitorProfile $visitor, \DateTimeInterface $day): ?VisitorDailyVisit
    {
        return $this->findOneBy([
            'visitorProfile' => $visitor,
            'visitedOn' => $day,
        ]);
    }
}
