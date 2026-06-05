<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\PaiementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class PaiementController extends AbstractController
{
    /**
     * Affiche le formulaire de paiement Stripe pour une réservation acceptée.
     *
     * @Route("user/paiement/{id}", name="paiement_form", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function afficherFormulairePaiement(
        int $id,
        ReservationRepository $repo,
        PaiementService $paiementService,
        EntityManagerInterface $em
    ): Response {
        // 🔎 On récupère la réservation
        $reservation = $repo->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException("Réservation introuvable.");
        }

        // 🔒 L'utilisateur connecté doit être le passager
        if ($reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Accès refusé à cette réservation.");
        }

        // ✅ La réservation doit être acceptée avant de payer
        if ($reservation->getStatut() !== 'acceptee') {
            $this->addFlash('warning', 'Vous ne pouvez payer que si le conducteur a accepté votre réservation.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        // 🔒 Vérifie que le paiement est bien initialisé
        if (!$reservation->getPaiement()) {
            throw $this->createNotFoundException("Aucun paiement associé à cette réservation.");
        }

        // 💳 On autorise (ou récupère) le paiement Stripe
        $clientSecret = $paiementService->autoriserPaiement($reservation);

        // 💾 Enregistre le paymentIntentId + statut = 'autorise'
        $em->flush();

        // 👇 Affiche le formulaire de paiement Stripe
        return $this->render('paiement/formulaire.html.twig', [
            'clientSecret' => $clientSecret,
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'],
            'total' => $reservation->getPaiement()->getMontant(), // ✅ on utilise Paiement
            'reservation' => $reservation,
        ]);
    }

    /**
     * Affiche la page de confirmation après paiement autorisé.
     * @Route("user/paiement/confirmation/{id}", name="paiement_confirmation", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function confirmation(int $id, ReservationRepository $repo): Response
    {
        $reservation = $repo->find($id);

        // 🔐 Vérifie que la réservation existe et appartient bien à l'utilisateur
        if (!$reservation || $reservation->getPassager() !== $this->getUser()) {
            throw $this->createNotFoundException("Accès interdit ou réservation introuvable.");
        }

        // ✅ Met à jour le statut si pas encore marqué comme payé
        $paiement = $reservation->getPaiement();
        if (!$paiement || !in_array($paiement->getStatut(), ['autorise', 'capture'], true)) {
            $this->addFlash('warning', 'Le paiement n’est pas encore confirmé. Si vous venez de payer, patientez quelques secondes puis actualisez vos réservations.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        return $this->render('paiement/confirmation.html.twig', [
            'reservation' => $reservation
        ]);
    }

}
