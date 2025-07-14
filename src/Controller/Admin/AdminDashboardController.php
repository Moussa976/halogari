<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Repository\TrajetRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Repository\DocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class AdminDashboardController extends AbstractController
{
    /**
     * @Route("/admin", name="admin_dashboard")
     */
    public function dashboard(
        TrajetRepository $trajetRepo,
        ReservationRepository $resaRepo,
        UserRepository $userRepo,
        DocumentRepository $docRepo,
        EntityManagerInterface $em
    ): Response {
        // Derniers trajets et réservations
        $lastTrajets = $trajetRepo->findBy([], ['createdAt' => 'DESC'], 10);
        $lastReservations = $resaRepo->findBy([], ['createdAt' => 'DESC'], 10);

        // Préparer les données mensuelles pour les graphiques
        $labels = [];
        $trajetsPerMonth = [];
        $reservationsPerMonth = [];

        $now = new \DateTimeImmutable();
        for ($i = 11; $i >= 0; $i--) {
            $date = $now->modify("-$i months");
            $label = $date->format('M Y');
            $labels[] = $label;

            $monthStart = $date->modify('first day of this month')->setTime(0, 0);
            $monthEnd = $date->modify('last day of this month')->setTime(23, 59, 59);

            // Nombre de trajets créés ce mois
            $trajetsCount = $trajetRepo->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getSingleScalarResult();

            // Nombre de réservations créées ce mois
            $reservationsCount = $resaRepo->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $trajetsPerMonth[] = (int) $trajetsCount;
            $reservationsPerMonth[] = (int) $reservationsCount;
        }

        return $this->render('admin/dashboard.html.twig', [
            'count_trajets' => $trajetRepo->count([]),
            'count_reservations' => $resaRepo->count([]),
            'count_users' => $userRepo->count([]),
            'count_documents' => $docRepo->count(['status' => Document::STATUS_APPROVED]),

            'last_trajets' => $lastTrajets,
            'last_reservations' => $lastReservations,

            'chart_labels' => $labels,
            'chart_trajets' => $trajetsPerMonth,
            'chart_reservations' => $reservationsPerMonth,
        ]);
    }
}
