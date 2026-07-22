<?php

namespace App\Service;

use App\Entity\VisitorDailyStat;
use App\Entity\VisitorDailyVisit;
use App\Entity\VisitorProfile;
use App\Repository\VisitorDailyStatRepository;
use App\Repository\VisitorDailyVisitRepository;
use App\Repository\VisitorProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

class VisitorAnalyticsTracker
{
    private const COOKIE_NAME = 'hg_visitor';

    private EntityManagerInterface $em;
    private VisitorProfileRepository $visitors;
    private VisitorDailyStatRepository $dailyStats;
    private VisitorDailyVisitRepository $dailyVisits;
    private Security $security;

    public function __construct(
        EntityManagerInterface $em,
        VisitorProfileRepository $visitors,
        VisitorDailyStatRepository $dailyStats,
        VisitorDailyVisitRepository $dailyVisits,
        Security $security
    ) {
        $this->em = $em;
        $this->visitors = $visitors;
        $this->dailyStats = $dailyStats;
        $this->dailyVisits = $dailyVisits;
        $this->security = $security;
    }

    public function track(Request $request, Response $response): void
    {
        if (!$this->shouldTrack($request, $response)) {
            return;
        }

        $visitorId = (string) $request->cookies->get(self::COOKIE_NAME, '');
        if (!preg_match('/^[a-f0-9]{40}$/', $visitorId)) {
            $visitorId = bin2hex(random_bytes(20));
            $response->headers->setCookie(
                Cookie::create(self::COOKIE_NAME, $visitorId)
                    ->withExpires(strtotime('+13 months'))
                    ->withPath('/')
                    ->withSecure($request->isSecure())
                    ->withHttpOnly(true)
                    ->withSameSite(Cookie::SAMESITE_LAX)
            );
        }

        $visitorKey = hash('sha256', $visitorId);
        $visitor = $this->visitors->findOneBy(['visitorKey' => $visitorKey]);
        if (!$visitor) {
            $visitor = (new VisitorProfile())->setVisitorKey($visitorKey);
            $this->em->persist($visitor);
        }

        $path = $request->getPathInfo();
        $userAgent = (string) $request->headers->get('User-Agent', '');
        $visitor->recordPageView($path, $userAgent !== '' ? hash('sha256', $userAgent) : null);

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Indian/Mayotte'));
        $dailyVisit = $this->dailyVisits->findOneForVisitorAndDay($visitor, $today);
        $newDailyVisitor = false;
        if (!$dailyVisit) {
            $dailyVisit = (new VisitorDailyVisit())
                ->setVisitorProfile($visitor)
                ->setVisitedOn($today);
            $this->em->persist($dailyVisit);
            $newDailyVisitor = true;
        }
        $dailyVisit->recordPageView();

        $dailyStat = $this->dailyStats->findOneForDay($today);
        if (!$dailyStat) {
            $dailyStat = (new VisitorDailyStat())->setVisitedOn($today);
            $this->em->persist($dailyStat);
        }
        $dailyStat->addPageView($newDailyVisitor);

        $this->em->flush();
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if ($request->getMethod() !== 'GET') {
            return false;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return false;
        }

        if ($request->isXmlHttpRequest()) {
            return false;
        }

        $route = (string) $request->attributes->get('_route', '');
        if ($route === '' || strpos($route, '_') === 0 || strpos($route, 'admin_') === 0 || strpos($route, 'api_') === 0) {
            return false;
        }

        $path = $request->getPathInfo();
        foreach (['/admin', '/api', '/push', '/webhook', '/_wdt', '/_profiler'] as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return false;
            }
        }

        $accept = (string) $request->headers->get('Accept', '');

        return $accept === '' || strpos($accept, 'text/html') !== false || strpos($accept, '*/*') !== false;
    }
}
