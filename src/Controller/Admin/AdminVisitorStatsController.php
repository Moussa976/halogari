<?php

namespace App\Controller\Admin;

use App\Repository\VisitorDailyStatRepository;
use App\Repository\VisitorProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminVisitorStatsController extends AbstractController
{
    /**
     * @Route("/admin/statistiques-visiteurs", name="admin_visitor_stats", methods={"GET"})
     */
    public function index(
        VisitorDailyStatRepository $dailyStats,
        VisitorProfileRepository $visitorProfiles
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Indian/Mayotte'));
        $todayStat = $dailyStats->findOneForDay($today);
        $lastDays = $dailyStats->findLastDays(30);
        $chartDays = array_reverse($lastDays);

        return $this->render('admin/visitor_stats/index.html.twig', [
            'todayStat' => $todayStat,
            'totalVisitors' => $visitorProfiles->count([]),
            'totalPageViews' => $dailyStats->totalPageViews(),
            'lastDays' => $lastDays,
            'chartLabels' => array_map(static fn($stat): string => $stat->getVisitedOn()->format('d/m'), $chartDays),
            'chartUniqueVisitors' => array_map(static fn($stat): int => $stat->getUniqueVisitors(), $chartDays),
            'chartPageViews' => array_map(static fn($stat): int => $stat->getPageViews(), $chartDays),
        ]);
    }
}
