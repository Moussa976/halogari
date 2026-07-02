<?php

namespace App\Repository;

use App\Entity\AdminSeenAction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminSeenAction>
 */
class AdminSeenActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminSeenAction::class);
    }

    /**
     * @param int[] $itemIds
     * @return array<int, bool>
     */
    public function seenMap(User $admin, string $type, array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if ($itemIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('s')
            ->select('s.itemId')
            ->andWhere('s.admin = :admin')
            ->andWhere('s.actionType = :type')
            ->andWhere('s.itemId IN (:ids)')
            ->setParameter('admin', $admin)
            ->setParameter('type', $type)
            ->setParameter('ids', $itemIds)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['itemId']] = true;
        }

        return $map;
    }

    public function countUnseen(User $admin, string $type, array $itemIds): int
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if ($itemIds === []) {
            return 0;
        }

        $seen = $this->seenMap($admin, $type, $itemIds);

        return count(array_filter($itemIds, static fn (int $id): bool => !isset($seen[$id])));
    }

    public function markSeen(User $admin, string $type, int $itemId): void
    {
        if (!in_array($type, [AdminSeenAction::TYPE_RESERVATION, AdminSeenAction::TYPE_DOCUMENT, AdminSeenAction::TYPE_PAIEMENT], true) || $itemId <= 0) {
            return;
        }

        $seen = $this->findOneBy([
            'admin' => $admin,
            'actionType' => $type,
            'itemId' => $itemId,
        ]);

        if (!$seen) {
            $seen = (new AdminSeenAction())
                ->setAdmin($admin)
                ->setActionType($type)
                ->setItemId($itemId);
            $this->getEntityManager()->persist($seen);
        }

        $seen->markSeen();
        $this->getEntityManager()->flush();
    }
}
