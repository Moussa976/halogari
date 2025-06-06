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

        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();

        return $resultSet->fetchAllAssociative();
    }


    public function findByRecherche(string $depart, string $arrivee, string $date, int $places): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('LOWER(t.depart) = LOWER(:depart)')
            ->andWhere('LOWER(t.arrivee) = LOWER(:arrivee)')
            ->andWhere('t.dateTrajet >= :startOfDay')
            ->andWhere('t.dateTrajet < :endOfDay')
            ->andWhere('t.placesDisponibles >= :places')
            ->setParameter('depart', $depart)
            ->setParameter('arrivee', $arrivee)
            ->setParameter('places', $places);

        // Gestion de la date
        $dateObj = new \DateTime($date);
        $startOfDay = (clone $dateObj)->setTime(0, 0, 0);
        $endOfDay = (clone $dateObj)->setTime(23, 59, 59);

        $qb->setParameter('startOfDay', $startOfDay);
        $qb->setParameter('endOfDay', $endOfDay);

        return $qb
            ->orderBy('t.dateTrajet', 'ASC')
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




    //    /**
//     * @return Trajet[] Returns an array of Trajet objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    //    public function findOneBySomeField($value): ?Trajet
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
