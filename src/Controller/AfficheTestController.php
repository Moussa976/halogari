<?php

namespace App\Controller;

use App\Entity\Trajet;
use App\Entity\User;
use App\Service\AfficheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AfficheTestController extends AbstractController
{
    /**
     * @Route("/affiche/test", name="affiche_test", methods={"GET"})
     */
    public function testAffiche(Request $request, AfficheService $afficheService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $trajet = new Trajet();
        $trajet->setDepart($request->query->get('depart', 'M\'Tsahara'));
        $trajet->setArrivee($request->query->get('arrivee', 'Mamoudzou'));
        $trajet->setDateTrajet(new \DateTime($request->query->get('date', '+2 days')));
        $trajet->setHeureTrajet(new \DateTime($request->query->get('heure', '14:00')));
        $trajet->setPlacesDisponibles((int) $request->query->get('places', 3));
        $trajet->setPrix((string) $request->query->get('prix', '6.00'));

        $conducteur = new User();
        $conducteur->setPrenom($request->query->get('chauffeur', 'Moussa'));
        $conducteur->setNom($request->query->get('nom', 'Tata'));
        $trajet->setConducteur($conducteur);

        $imagePath = $afficheService->generate($trajet);

        return $this->render('affiche_test/result.html.twig', [
            'imagePath' => $imagePath,
            'trajet' => $trajet,
        ]);
    }
}
