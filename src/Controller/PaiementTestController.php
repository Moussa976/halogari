<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\PaiementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaiementTestController extends AbstractController
{
    /**
     * @Route("/test/paiement/autoriser/{id}", name="test_paiement_autoriser")
     */
    public function autoriserPaiement(int $id, ReservationRepository $repo, PaiementService $paiement): Response
    {
        $reservation = $repo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        $clientSecret = $paiement->autoriserPaiement($reservation);

        return new Response('Paiement autorisé. Client Secret : ' . $clientSecret);
    }

    /**
     * @Route("/test/paiement/capturer/{id}", name="test_paiement_capturer")
     */
    public function capturerPaiement(int $id, ReservationRepository $repo, PaiementService $paiement): Response
    {
        $reservation = $repo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        $paiement->capturerPaiement($reservation);

        return new Response('Paiement capturé avec succès.');
    }

    /**
     * @Route("/test/paiement/annuler/{id}", name="test_paiement_annuler")
     */
    public function annulerPaiement(int $id, ReservationRepository $repo, PaiementService $paiement): Response
    {
        $reservation = $repo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        $paiement->annulerPaiement($reservation);

        return new Response('Paiement annulé avec succès.');
    }

    /**
     * @Route("/paiement/{id}", name="paiement_form")
     */
    public function afficherFormulairePaiement(int $id, ReservationRepository $repo, PaiementService $paiement): Response
    {
        $reservation = $repo->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException("Réservation introuvable");
        }

        // Autorise le paiement (ou récupère le client_secret si déjà fait)
        $clientSecret = $paiement->autoriserPaiement($reservation);


        return $this->render('paiement/formulaire.html.twig', [
            'clientSecret' => $clientSecret,
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'],
            'total' => $reservation->getPrixTotal(), // 💰 Montant total à payer
            'reservation' => $reservation, // 👈 Ajout du trajet réservé
        ]);


    }
}
