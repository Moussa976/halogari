<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use App\Service\PaiementService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaiementController extends AbstractController
{
    /**
     * Affiche le formulaire Stripe pour une réservation acceptée.
     *
     * @Route("/user/paiement/{id}", name="paiement_form", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function afficherFormulairePaiement(
        int $id,
        ReservationRepository $repo,
        PaiementService $paiementService,
        EntityManagerInterface $em
    ): Response {
        $reservation = $repo->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        if ($reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé à cette réservation.');
        }

        if ($reservation->getStatut() !== 'acceptee') {
            $this->addFlash('warning', 'Vous ne pouvez payer que si le conducteur a accepté votre réservation.');
            return $this->redirectToRoute('app_user_reservation', ['id' => $reservation->getId()]);
        }

        $stripePublicKey = $_ENV['STRIPE_PUBLIC_KEY'] ?? '';
        if ($stripePublicKey === '') {
            $this->addFlash('error', 'La configuration Stripe est incomplète. Le paiement est temporairement indisponible.');
            return $this->redirectToRoute('app_user_reservation', ['id' => $reservation->getId()]);
        }

        try {
            $clientSecret = $paiementService->autoriserPaiement($reservation);
            $em->flush();
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage() ?: 'Le paiement ne peut pas être initialisé pour le moment.');
            return $this->redirectToRoute('app_user_reservation', ['id' => $reservation->getId()]);
        }

        return $this->render('paiement/formulaire.html.twig', [
            'clientSecret' => $clientSecret,
            'stripePublicKey' => $stripePublicKey,
            'total' => $reservation->getPaiement()->getMontant(),
            'reservation' => $reservation,
        ]);
    }

    /**
     * Affiche la confirmation après paiement capturé.
     *
     * @Route("/user/paiement/confirmation/{id}", name="paiement_confirmation", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function confirmation(int $id, ReservationRepository $repo): Response
    {
        $reservation = $repo->find($id);

        if (!$reservation || $reservation->getPassager() !== $this->getUser()) {
            throw $this->createNotFoundException('Accès interdit ou réservation introuvable.');
        }

        $paiement = $reservation->getPaiement();
        if (!$paiement || $paiement->getStatut() !== 'capture') {
            $this->addFlash('warning', "Le paiement n'est pas encore confirmé. Si vous venez de payer, patientez quelques secondes puis actualisez vos réservations.");
            return $this->redirectToRoute('app_user_reservation', ['id' => $reservation->getId()]);
        }

        return $this->render('paiement/confirmation.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
