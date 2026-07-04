<?php

namespace App\Repository;

use App\Entity\Trajet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trajet>
 *
 * @method Trajet|null find($id, $lockMode = null, $lockVersion = null)
 * @method Trajet|null findOneBy(array $criteria, array $orderBy = null)
 * @method Trajet[]    findAll()
 * @method Trajet[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrajetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trajet::class);
    }

    public function add(Trajet $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Trajet $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findPopularTrajets(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT t.depart, t.arrivee, COUNT(r.id) AS total
            FROM reservation r
            JOIN trajet t ON r.trajet_id = t.id
            JOIN `user` u ON t.conducteur_id = u.id
            WHERE t.date_trajet = :today
              AND (t.annule IS NULL OR t.annule = 0)
              AND u.disabled_at IS NULL
            GROUP BY t.depart, t.arrivee
            ORDER BY total DESC
            LIMIT 3
        ';

        return $conn->prepare($sql)->executeQuery([
            'today' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ])->fetchAllAssociative();
    }

    /**
     * @return Trajet[]
     */
    public function findRecentlyPublishedAvailable(int $limit = 3): array
    {
        $today = (new \DateTime())->setTime(0, 0, 0);

        return $this->createQueryBuilder('t')
            ->innerJoin('t.conducteur', 'c')
            ->where('t.dateTrajet >= :today')
            ->andWhere('t.placesDisponibles > 0')
            ->andWhere('t.annule IS NULL OR t.annule = false')
            ->andWhere('c.disabledAt IS NULL')
            ->setParameter('today', $today)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.dateTrajet', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findMostReservedRoutes(int $limit = 3): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT t.depart, t.arrivee, COUNT(r.id) AS total
            FROM reservation r
            JOIN trajet t ON r.trajet_id = t.id
            JOIN `user` u ON t.conducteur_id = u.id
            WHERE (t.annule IS NULL OR t.annule = 0)
              AND u.disabled_at IS NULL
            GROUP BY t.depart, t.arrivee
            ORDER BY total DESC
            LIMIT :limit
        ';

        return $conn->executeQuery($sql, [
            'limit' => $limit,
        ], [
            'limit' => \PDO::PARAM_INT,
        ])->fetchAllAssociative();
    }

    /**
     * @return Trajet[]
     */
    public function findAvailableUpcoming(int $limit = 10): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('t')
            ->innerJoin('t.conducteur', 'c')
            ->where('t.dateTrajet >= :today')
            ->andWhere('t.placesDisponibles > 0')
            ->andWhere('t.annule IS NULL OR t.annule = false')
            ->andWhere('c.disabledAt IS NULL')
            ->setParameter('today', $now->setTime(0, 0, 0))
            ->orderBy('t.dateTrajet', 'ASC')
            ->addOrderBy('t.heureTrajet', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Trajet[]
     */
    public function findByRecherche(string $depart, string $arrivee, string $date, int $places, array $departAliases = [], array $arriveeAliases = []): array
    {
        $dateObj = new \DateTime($date);
        $startOfDay = (clone $dateObj)->setTime(0, 0, 0);
        $endOfDay = (clone $dateObj)->setTime(23, 59, 59);
        $departAliases = array_values(array_unique(array_filter($departAliases ?: [$depart])));
        $arriveeAliases = array_values(array_unique(array_filter($arriveeAliases ?: [$arrivee])));

        return $this->createQueryBuilder('t')
            ->innerJoin('t.conducteur', 'c')
            ->where('LOWER(t.depart) IN (:departAliases)')
            ->andWhere('LOWER(t.arrivee) IN (:arriveeAliases)')
            ->andWhere('t.dateTrajet >= :startOfDay')
            ->andWhere('t.dateTrajet < :endOfDay')
            ->andWhere('t.annule IS NULL OR t.annule = false')
            ->andWhere('c.disabledAt IS NULL')
            ->addSelect('CASE WHEN t.placesDisponibles >= :places THEN 0 ELSE 1 END AS HIDDEN availabilityRank')
            ->setParameter('departAliases', array_map('mb_strtolower', $departAliases))
            ->setParameter('arriveeAliases', array_map('mb_strtolower', $arriveeAliases))
            ->setParameter('places', $places)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('availabilityRank', 'ASC')
            ->addOrderBy('t.dateTrajet', 'ASC')
            ->addOrderBy('t.heureTrajet', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByID(int $id): ?Trajet
    {
        return $this->createQueryBuilder('t')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
