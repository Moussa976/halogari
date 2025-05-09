<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TrajetController extends AbstractController
{
    /**
     * @Route("/chercher", name="app_chercher")
     */
    public function chercher(Request $request): Response
    {
        return $this->render('trajet/chercher.html.twig');
    }

    /**
     * @Route("/publier", name="app_publier")
     */
    public function publier(): Response
    {
        return $this->render('trajet/publier.html.twig');
    }

    /**
     * @Route("/trajet/{id}", name="app_trajet_show")
     */
    public function show(int $id): Response
    {
        // affichage d'un trajet
        return $this->render('trajet/show.html.twig', ['id' => $id]);
    }
}
