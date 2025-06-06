<?php

namespace App\Controller;

use App\Repository\TrajetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="app_home")
     */
    public function index(TrajetRepository $trajetRepository): Response
    {
        // Simulation de "popularTrajets" comme dans Express.js
        $popularTrajets = $trajetRepository->findPopularTrajets(); // On définira cette méthode custom

        return $this->render('home/index.html.twig', [
            'popularTrajets' => $popularTrajets,
        ]);
    }

    /**
     * @Route("/securite", name="app_securite")
     */
    public function securite(): Response
    {
        return $this->render('security/securite.html.twig');
    }
}
