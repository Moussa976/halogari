<?php

namespace App\Repository;

use App\Entity\SmsLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SmsLog>
 */
class SmsLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SmsLog::class);
    }

    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('sms')
            ->leftJoin('sms.user', 'u')
            ->addSelect('u')
            ->leftJoin('sms.reservation', 'r')
            ->addSelect('r')
            ->orderBy('sms.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
