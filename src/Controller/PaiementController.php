<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use App\Service\PaiementService;
use App\Service\SmsService;
use App\Service\StripeConfigService;
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
        StripeConfigService $stripeConfig,
        EntityManagerInterface $em,
        NotificationService $notificationService,
        SmsService $smsService
    ): Response {
        $reservation = $repo->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        if ($reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé à cette réservation.');
        }

        try {
            if ($paiementService->synchroniserPaiementStripe($reservation)) {
                $notificationService->envoyerPaiementCapture($reservation);
                $smsService->envoyerPlaceConfirmeeAvecCode($reservation);
            }
        } catch (\Throwable $exception) {
            // Le formulaire reste disponible si Stripe n'est pas joignable ici.
        }

        if (
            $reservation->getPaiement()
            && in_array($reservation->getPaiement()->getStatut(), ['autorise', 'capture'], true)
        ) {
            return $this->redirectToRoute('paiement_confirmation', ['id' => $reservation->getId()]);
        }

        if ($reservation->getStatut() !== 'acceptee') {
            $this->addFlash('warning', 'Vous ne pouvez payer que si le conducteur a accepté votre réservation.');
            return $this->redirectToRoute('app_user_reservation', ['id' => $reservation->getId()]);
        }

        $stripePublicKey = $stripeConfig->publicKey();
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
     * Affiche la confirmation après enregistrement ou confirmation du paiement.
     *
     * @Route("/user/paiement/confirmation/{id}", name="paiement_confirmation", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function confirmation(
        int $id,
        ReservationRepository $repo,
        PaiementService $paiementService,
        NotificationService $notificationService,
        SmsService $smsService
    ): Response
    {
        $reservation = $repo->find($id);

        if (!$reservation || $reservation->getPassager() !== $this->getUser()) {
            throw $this->createNotFoundException('Accès interdit ou réservation introuvable.');
        }

        try {
            if ($paiementService->synchroniserPaiementStripe($reservation)) {
                $notificationService->envoyerPaiementCapture($reservation);
                $smsService->envoyerPlaceConfirmeeAvecCode($reservation);
            }
        } catch (\Throwable $exception) {
            // Le webhook Stripe reste le filet de securite si la synchronisation directe echoue.
        }

        $paiement = $reservation->getPaiement();
        if (!$paiement || !in_array($paiement->getStatut(), ['autorise', 'capture'], true)) {
            $this->addFlash('warning', "Le paiement est en cours d'enregistrement. Si votre banque l'a validé, HaloGari mettra votre réservation à jour dans quelques secondes.");
            return $this->redirectToRoute('app_user_reservation', ['id' => $reservation->getId()]);
        }

        return $this->render('paiement/confirmation.html.twig', [
            'reservation' => $reservation,
            'paiement' => $paiement,
        ]);
    }
}
