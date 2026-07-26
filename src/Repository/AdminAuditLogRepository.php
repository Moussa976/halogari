<?php

namespace App\Repository;

use App\Entity\AdminAuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminAuditLog>
 */
class AdminAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminAuditLog::class);
    }

    /**
     * @return AdminAuditLog[]
     */
    public function findPendingDigest(\DateTimeImmutable $since, \DateTimeImmutable $until, int $limit = 500): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.digestSentAt IS NULL')
            ->andWhere('l.createdAt >= :since')
            ->andWhere('l.createdAt <= :until')
            ->setParameter('since', $since)
            ->setParameter('until', $until)
            ->orderBy('l.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AdminAuditLog[]
     */
    public function findByActions(array $actions, int $limit = 80): array
    {
        if ($actions === []) {
            return [];
        }

        return $this->createQueryBuilder('l')
            ->where('l.action IN (:actions)')
            ->setParameter('actions', $actions)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByActionsSince(array $actions, \DateTimeImmutable $since): int
    {
        if ($actions === []) {
            return 0;
        }

        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.action IN (:actions)')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('actions', $actions)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
