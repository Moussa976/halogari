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
        return $this->render('home/index.html.twig', [
            'popularTrajets' => $trajetSearch->popularRoutes(),
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
