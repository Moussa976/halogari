<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
    public function publier(Request $request, SessionInterface $session): Response
    {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Enregistre l’URL cible dans la session pour y revenir après login
            $session->set('_security.main.target_path', $request->getUri());

            $this->addFlash('error', 'Vous devez être connecté pour publier un trajet.');
            return $this->redirectToRoute('app_login');
        }
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
