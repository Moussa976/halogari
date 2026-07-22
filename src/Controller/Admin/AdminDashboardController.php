<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Repository\TrajetRepository;
use App\Repository\UserRepository;
use App\Repository\VisitorDailyStatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminDashboardController extends AbstractController
{
    /**
     * @Route("/admin", name="admin_dashboard", methods={"GET"})
     */
    public function dashboard(
        TrajetRepository $trajetRepo,
        ReservationRepository $resaRepo,
        UserRepository $userRepo,
        DocumentRepository $docRepo,
        PaiementRepository $paiementRepo,
        VisitorDailyStatRepository $visitorDailyStats,
        EntityManagerInterface $em
    ): Response {
        $lastTrajets = $trajetRepo->findBy([], ['createdAt' => 'DESC'], 10);
        $lastReservations = $resaRepo->findBy([], ['createdAt' => 'DESC'], 10);
        $lastDocuments = $docRepo->findBy([], ['dateDocument' => 'DESC'], 6);
        $lastPaiements = $paiementRepo->findBy([], ['createdAt' => 'DESC'], 6);

        $labels = [];
        $trajetsPerMonth = [];
        $reservationsPerMonth = [];

        $now = new \DateTimeImmutable();
        for ($i = 11; $i >= 0; $i--) {
            $date = $now->modify("-$i months");
            $labels[] = $date->format('M Y');

            $monthStart = $date->modify('first day of this month')->setTime(0, 0);
            $monthEnd = $date->modify('last day of this month')->setTime(23, 59, 59);

            $trajetsPerMonth[] = (int) $trajetRepo->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $reservationsPerMonth[] = (int) $resaRepo->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getSingleScalarResult();
        }

        $capturedRevenue = (float) $paiementRepo->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.montant), 0)')
            ->where('p.statut = :status')
            ->setParameter('status', 'capture')
            ->getQuery()
            ->getSingleScalarResult();

        $refundedRevenue = (float) $paiementRepo->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.montant), 0)')
            ->where('p.statut = :status')
            ->setParameter('status', 'rembourse')
            ->getQuery()
            ->getSingleScalarResult();
        $todayVisitorStat = $visitorDailyStats->findOneForDay(new \DateTimeImmutable('today', new \DateTimeZone('Indian/Mayotte')));

        return $this->render('admin/dashboard.html.twig', [
            'count_trajets' => $trajetRepo->count([]),
            'count_reservations' => $resaRepo->count([]),
            'count_users' => $userRepo->count([]),
            'count_documents' => $docRepo->count(['status' => Document::STATUS_APPROVED]),
            'count_documents_pending' => $docRepo->count(['status' => Document::STATUS_PENDING]),
            'count_reservations_pending' => $resaRepo->count(['statut' => 'en_attente']),
            'count_payments_authorized' => $paiementRepo->count(['statut' => 'autorise']),
            'count_payments_captured' => $paiementRepo->count(['statut' => 'capture']),
            'count_payments_refunded' => $paiementRepo->count(['statut' => 'rembourse']),
            'captured_revenue' => $capturedRevenue,
            'refunded_revenue' => $refundedRevenue,
            'today_unique_visitors' => $todayVisitorStat ? $todayVisitorStat->getUniqueVisitors() : 0,
            'today_page_views' => $todayVisitorStat ? $todayVisitorStat->getPageViews() : 0,

            'last_trajets' => $lastTrajets,
            'last_reservations' => $lastReservations,
            'last_documents' => $lastDocuments,
            'last_paiements' => $lastPaiements,

            'chart_labels' => $labels,
            'chart_trajets' => $trajetsPerMonth,
            'chart_reservations' => $reservationsPerMonth,
        ]);
    }
}
