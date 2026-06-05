<?php

namespace App\Controller;

use App\Entity\Trajet;
use App\Service\AfficheService;
use App\Service\MetaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AfficheTestController extends AbstractController
{
    /**
     * @Route("/affiche/test", name="affiche_test", methods={"GET"})
     */
    public function testAffiche(AfficheService $afficheService, MetaService $publisher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $trajet = new Trajet();
        $trajet->setDepart('Mamoudzou');
        $trajet->setArrivee('Dembéni');
        $trajet->setDateTrajet(new \DateTime('+2 days'));
        $trajet->setHeureTrajet(new \DateTime('14:00'));
        $trajet->setPlacesDisponibles(3);
        $trajet->setPrix(2.5);

        $imagePath = $afficheService->generate($trajet); // ex: /uploads/affiches/trajet_xxx.png
        $fullPath = $this->getParameter('kernel.project_dir') . '/public' . $imagePath;

        if (!file_exists($fullPath)) {
            throw $this->createNotFoundException('Image introuvable.');
        }

        $caption = sprintf(
            "🚗 Nouveau trajet disponible ! %s → %s le %s à %s\n💺 %d places disponibles – %.2f €/place",
            $trajet->getDepart(),
            $trajet->getArrivee(),
            $trajet->getDateTrajet()->format('d/m/Y'),
            $trajet->getHeureTrajet()->format('H:i'),
            $trajet->getPlacesDisponibles(),
            $trajet->getPrix()
        );

        $publisher->publierSurFacebook($fullPath, $caption);

        return $this->render('affiche_test/result.html.twig', [
            'imagePath' => $imagePath,
        ]);
    }
}
