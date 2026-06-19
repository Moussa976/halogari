<?php

namespace App\Repository;

use App\Entity\Paiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paiement>
 *
 * @method Paiement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Paiement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Paiement[]    findAll()
 * @method Paiement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paiement::class);
    }

    public function add(Paiement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Paiement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Paiement[]
     */
    public function findAdminActionRequired(int $limit = 5): array
    {
        $paiements = $this->createQueryBuilder('p')
            ->leftJoin('p.reservation', 'r')
            ->addSelect('r')
            ->leftJoin('r.trajet', 't')
            ->addSelect('t')
            ->leftJoin('r.commissions', 'c')
            ->addSelect('c')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', 'capture')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $aTraiter = array_values(array_filter($paiements, [$this, 'requiresAdminAction']));

        return array_slice($aTraiter, 0, $limit);
    }

    public function countAdminActionRequired(): int
    {
        return count($this->findAdminActionRequired(PHP_INT_MAX));
    }

    private function requiresAdminAction(Paiement $paiement): bool
    {
        if ($paiement->getStatut() !== 'capture' || $paiement->getMontantDisponible() <= 0) {
            return false;
        }

        $reservation = $paiement->getReservation();
        if (!$reservation) {
            return true;
        }

        if ($reservation->getCommissions()->count() > 0) {
            return false;
        }

        if ($reservation->getStatut() === 'annulee') {
            return true;
        }

        $trajet = $reservation->getTrajet();
        if (!$trajet) {
            return true;
        }

        return $trajet->isPretPourVersement() || $trajet->getStatutOperationnel() === 'litige';
    }

//    /**
//     * @return Paiement[] Returns an array of Paiement objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Paiement
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
