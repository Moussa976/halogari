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
            GROUP BY t.depart, t.arrivee
            ORDER BY total DESC
            LIMIT 3
        ';

        return $conn->prepare($sql)->executeQuery()->fetchAllAssociative();
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
    public function findByRecherche(string $depart, string $arrivee, string $date, int $places): array
    {
        $dateObj = new \DateTime($date);
        $startOfDay = (clone $dateObj)->setTime(0, 0, 0);
        $endOfDay = (clone $dateObj)->setTime(23, 59, 59);

        return $this->createQueryBuilder('t')
            ->innerJoin('t.conducteur', 'c')
            ->where('LOWER(t.depart) = LOWER(:depart)')
            ->andWhere('LOWER(t.arrivee) = LOWER(:arrivee)')
            ->andWhere('t.dateTrajet >= :startOfDay')
            ->andWhere('t.dateTrajet < :endOfDay')
            ->andWhere('t.annule IS NULL OR t.annule = false')
            ->andWhere('c.disabledAt IS NULL')
            ->addSelect('CASE WHEN t.placesDisponibles >= :places THEN 0 ELSE 1 END AS HIDDEN availabilityRank')
            ->setParameter('depart', $depart)
            ->setParameter('arrivee', $arrivee)
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
