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
}
