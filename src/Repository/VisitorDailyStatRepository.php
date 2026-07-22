<?php

namespace App\Repository;

use App\Entity\VisitorDailyStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VisitorDailyStat>
 */
class VisitorDailyStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VisitorDailyStat::class);
    }

    public function findOneForDay(\DateTimeInterface $day): ?VisitorDailyStat
    {
        return $this->findOneBy(['visitedOn' => $day]);
    }

    /**
     * @return VisitorDailyStat[]
     */
    public function findLastDays(int $limit = 30): array
    {
        return $this->findBy([], ['visitedOn' => 'DESC'], $limit);
    }

    public function totalPageViews(): int
    {
        return (int) $this->_em->createQueryBuilder()
            ->select('COALESCE(SUM(s.pageViews), 0)')
            ->from(VisitorDailyStat::class, 's')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
