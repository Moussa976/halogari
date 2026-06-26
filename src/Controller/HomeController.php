<?php

namespace App\Controller;

use App\Application\Trajet\TrajetSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="app_home", methods={"GET"})
     */
    public function index(TrajetSearchService $trajetSearch): Response
    {
        $today = new \DateTimeImmutable('today');

        return $this->render('home/index.html.twig', [
            'popularTrajets' => $trajetSearch->popularRoutes(),
            'todaySearchDate' => $today->format('Y-m-d'),
            'todayDisplayDate' => $today->format('d/m/Y'),
        ]);
    }

    /**
     * @Route("/securite", name="app_securite", methods={"GET"})
     */
    public function securite(): Response
    {
        return $this->render('security/securite.html.twig');
    }
}
