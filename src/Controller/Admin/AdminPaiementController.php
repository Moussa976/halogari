<?php

// src/Controller/Admin/AdminPaiementController.php

namespace App\Controller\Admin;

use App\Entity\Paiement;
use App\Repository\PaiementRepository;
use App\Service\PaiementService;
use App\Service\StripePaiementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPaiementController extends AbstractController
{
    /**
     * Liste des paiements
     * @Route("/admin/paiements", name="admin_paiements")
     */
    public function index(PaiementRepository $paiementRepository): Response
    {
        $paiements = $paiementRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/paiements/index.html.twig', [
            'paiements' => $paiements,
        ]);
    }

    /**
     * @Route("/admin/paiements/{id}/capture", name="admin_paiement_capture", methods={"POST"})
     */
    public function capture(Paiement $paiement, PaiementService $paiementService): RedirectResponse
    {
        try {
            $paiementService->capturerPaiement($paiement->getPaymentIntentId());
            $this->addFlash('success', '✅ Paiement capturé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_paiements');
    }

    /**
     * @Route("/admin/paiements/{id}/cancel", name="admin_paiement_cancel", methods={"POST"})
     */
    public function cancel(Paiement $paiement, PaiementService $paiementService): RedirectResponse
    {
        try {
            $paiementService->annulerPaiement($paiement->getPaymentIntentId());
            $paiement->setStatut('annule');
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash('success', '🚫 Paiement annulé.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_paiements');
    }

    /**
     * @Route("/admin/paiements/{id}/refund", name="admin_paiement_refund", methods={"POST"})
     */
    public function refund(Paiement $paiement, PaiementService $paiementService): RedirectResponse
    {
        try {
            $paiementService->rembourserPaiement($paiement->getPaymentIntentId());
            $paiement->setStatut('rembourse');
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash('success', '💸 Paiement remboursé.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_paiements');
    }



}
