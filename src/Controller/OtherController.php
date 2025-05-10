<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OtherController extends AbstractController
{
    /**
     * @Route("/qui-sommes-nous", name="app_quisommesnous")
     */
    public function quisommesnous(): Response
    {
        return $this->render('others/qui-sommes-nous.html.twig', []);
    }

    /**
     * @Route("/conditions-utilisation", name="app_conditionsutisation")
     */
    public function conditionsutisation(): Response
    {
        return $this->render('others/conditions-utilisation.html.twig', []);
    }

    /**
     * @Route("/mentions-legales", name="app_mentionslegales")
     */
    public function mentionslegales(): Response
    {
        return $this->render('others/mentions-legales.html.twig', []);
    }

    /**
     * @Route("/confidentialite", name="app_confidentialite")
     */
    public function confidentialite(): Response
    {
        return $this->render('others/confidentialite.html.twig', []);
    }
}
