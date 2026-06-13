<?php

// src/Controller/Admin/AdminPaiementController.php

namespace App\Controller\Admin;

use App\Entity\Paiement;
use App\Entity\User;
use App\Repository\PaiementRepository;
use App\Service\AdminAuditLogger;
use App\Service\PaiementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPaiementController extends AbstractController
{
    /**
     * Liste des paiements
     * @Route("/admin/paiements", name="admin_paiements", methods={"GET"})
     */
    public function index(PaiementRepository $paiementRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $paiements = $paiementRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/paiements/index.html.twig', [
            'paiements' => $paiements,
        ]);
    }

    /**
     * @Route("/admin/paiements/{id}/capture", name="admin_paiement_capture", methods={"POST"})
     */
    public function capture(Paiement $paiement, Request $request, PaiementService $paiementService, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->assertValidPaymentToken($paiement, $request, 'capture');
        try {
            $paiementService->capturerPaiement($paiement->getPaymentIntentId());
            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'payment_capture', $paiement->getReservation() ? $paiement->getReservation()->getPassager() : null, ['paiementId' => $paiement->getId(), 'montant' => $paiement->getMontant()]);
            $this->addFlash('success', '✅ Paiement capturé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_paiements');
    }

    /**
     * @Route("/admin/paiements/{id}/transfer", name="admin_paiement_transfer", methods={"POST"})
     */
    public function transfer(Paiement $paiement, Request $request, PaiementService $paiementService, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->assertValidPaymentToken($paiement, $request, 'transfer');
        try {
            $paiementService->verserConducteur($paiement);
            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'payment_transfer_driver', $paiement->getReservation() && $paiement->getReservation()->getTrajet() ? $paiement->getReservation()->getTrajet()->getConducteur() : null, ['paiementId' => $paiement->getId(), 'montant' => $paiement->getMontant()]);
            $this->addFlash('success', 'Versement conducteur effectue.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_paiements');
    }

    /**
     * @Route("/admin/paiements/{id}/cancel", name="admin_paiement_cancel", methods={"POST"})
     */
    public function cancel(Paiement $paiement, Request $request, PaiementService $paiementService, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->assertValidPaymentToken($paiement, $request, 'cancel');
        try {
            $reservation = $paiement->getReservation();
            if (!$reservation) {
                throw new \RuntimeException('Reservation introuvable pour ce paiement.');
            }

            $paiementService->annulerPaiement($reservation);
            $paiement->setStatut('annule');
            $this->getDoctrine()->getManager()->flush();
            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'payment_cancel', $reservation->getPassager(), ['paiementId' => $paiement->getId(), 'montant' => $paiement->getMontant()]);

            $this->addFlash('success', '🚫 Paiement annulé.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_paiements');
    }

    /**
     * @Route("/admin/paiements/{id}/refund", name="admin_paiement_refund", methods={"POST"})
     */
    public function refund(Paiement $paiement, Request $request, PaiementService $paiementService, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->assertValidPaymentToken($paiement, $request, 'refund');
        try {
            $paiementService->rembourserPaiement($paiement->getPaymentIntentId());
            $paiement->setStatut('rembourse');
            $this->getDoctrine()->getManager()->flush();
            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'payment_refund', $paiement->getReservation() ? $paiement->getReservation()->getPassager() : null, ['paiementId' => $paiement->getId(), 'montant' => $paiement->getMontant()]);

            $this->addFlash('success', '💸 Paiement remboursé.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_paiements');
    }
    private function assertValidPaymentToken(Paiement $paiement, Request $request, string $action): void
    {
        if (!$this->isCsrfTokenValid('admin_paiement_' . $action . '_' . $paiement->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
        }
    }

}

