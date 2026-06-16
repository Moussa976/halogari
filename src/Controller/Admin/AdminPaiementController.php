<?php

namespace App\Controller\Admin;

use App\Entity\Paiement;
use App\Entity\User;
use App\Repository\PaiementRepository;
use App\Service\AdminAuditLogger;
use App\Service\PaiementEventLogger;
use App\Service\PaiementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPaiementController extends AbstractController
{
    /**
     * @Route("/admin/paiements", name="admin_paiements", methods={"GET"})
     */
    public function index(PaiementRepository $paiementRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/paiements/index.html.twig', [
            'paiements' => $paiementRepository->findBy([], ['createdAt' => 'DESC']),
            'mode' => 'paiements',
        ]);
    }

    /**
     * @Route("/admin/litiges-remboursements", name="admin_litiges_remboursements", methods={"GET"})
     */
    public function litigesRemboursements(PaiementRepository $paiementRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/paiements/index.html.twig', [
            'paiements' => $paiementRepository->findBy([], ['createdAt' => 'DESC']),
            'mode' => 'litiges',
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
            $paiementService->capturerPaiement((string) $paiement->getPaymentIntentId());
            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'payment_confirm', $paiement->getReservation() ? $paiement->getReservation()->getPassager() : null, ['paiementId' => $paiement->getId(), 'montant' => $paiement->getMontant()]);
            $this->addFlash('success', 'Paiement confirmé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
        }

        return $this->redirectBackToPayments($request);
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
            $this->addFlash('success', 'Versement conducteur effectué.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectBackToPayments($request);
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
                throw new \RuntimeException('Réservation introuvable pour ce paiement.');
            }

            $paiementService->annulerPaiement($reservation);
            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'payment_cancel', $reservation->getPassager(), ['paiementId' => $paiement->getId(), 'montant' => $paiement->getMontant()]);
            $this->addFlash('success', 'Paiement annulé.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectBackToPayments($request);
    }

    /**
     * @Route("/admin/paiements/{id}/refund", name="admin_paiement_refund", methods={"POST"})
     */
    public function refund(Paiement $paiement, Request $request, PaiementService $paiementService, PaiementEventLogger $eventLogger, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->assertValidPaymentToken($paiement, $request, 'refund');

        try {
            $reservation = $paiement->getReservation();
            if ($reservation && $reservation->getCommissions()->count() > 0) {
                throw new \RuntimeException('Le versement conducteur a déjà été effectué. Le remboursement doit être traité manuellement depuis Stripe et l’administration HaloGari.');
            }

            $paiementService->rembourserPaiement((string) $paiement->getPaymentIntentId());
            $paiement->setStatut('rembourse');
            $eventLogger->log($paiement, 'remboursement_admin', 'Remboursement administrateur', 'Remboursement lancé depuis l’espace admin.', $this->getUser() instanceof User ? $this->getUser() : null);
            $this->getDoctrine()->getManager()->flush();

            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'payment_refund', $paiement->getReservation() ? $paiement->getReservation()->getPassager() : null, ['paiementId' => $paiement->getId(), 'montant' => $paiement->getMontant()]);
            $this->addFlash('success', 'Paiement remboursé.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectBackToPayments($request);
    }

    private function assertValidPaymentToken(Paiement $paiement, Request $request, string $action): void
    {
        if (!$this->isCsrfTokenValid('admin_paiement_' . $action . '_' . $paiement->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
        }
    }

    private function redirectBackToPayments(Request $request): RedirectResponse
    {
        return $this->redirectToRoute($request->request->get('mode') === 'litiges' ? 'admin_litiges_remboursements' : 'admin_paiements');
    }
}
