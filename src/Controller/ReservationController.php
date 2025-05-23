<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Trajet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class ReservationController extends AbstractController
{
    /**
     * @Route("/mes-reservation", name="app_mesreservation")
     */
    public function index(): Response
    {
        return $this->render('reservation/index.html.twig', [
            'controller_name' => 'ReservationController',
        ]);
    }

    /**
     * @Route("/reservation/{id}", name="app_reservation", methods={"GET", "POST"})
     */
    public function create(Request $request, int $id, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            $this->addFlash('error', 'Vous devez être connecté pour réserver un trajet.');
            return $this->redirectToRoute('app_login');
        }
        $trajet = $em->getRepository(Trajet::class)->find($id);

        $places = (string) $request->request->get('placesReservees');

        // Vérification : null ou inférieur à 1
        if ($places <= 0) {
            $this->addFlash('danger', "Vous devez réserver au moins 1 place.");
            return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId(), "ledepart" => $trajet->getDepart(), "larrive" => $trajet->getArrivee(), "nbPlaceReservee" => $places]);
        }

        // Calcul du nombre de places déjà prises
        $placesDejaPrises = array_sum(array_map(
            fn($r) => $r->getPlacesReservees(),
            $trajet->getReservations()->toArray()
        ));

        $placesRestantes = $trajet->getPlaces() - $placesDejaPrises;

        if ($places > $placesRestantes) {
            $this->addFlash('danger', "Il ne reste que $placesRestantes place(s) disponibles pour ce trajet.");
            return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId(), "ledepart" => $trajet->getDepart(), "larrive" => $trajet->getArrivee(), "nbPlaceReservee" => $places]);
        }
        die();

        // Création de la réservation
        $reservation = new Reservation();
        $reservation->setTrajet($trajet);
        $reservation->setUser($this->getUser());
        $reservation->setPlacesReservees($places);
        $reservation->setPrixTotal($trajet->getPrix() * $places);

        $em->persist($reservation);
        $em->flush();

        $this->addFlash('success', "Réservation confirmée pour $places place(s).");

        return $this->redirectToRoute('reservation_show', ['id' => $reservation->getId()]);
    }

}
