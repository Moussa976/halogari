<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class AdminNavbarController extends AbstractController
{
    public function summary(
        ReservationRepository $reservationRepository,
        DocumentRepository $documentRepository,
        PaiementRepository $paiementRepository
    ): Response {
        $pendingReservations = $reservationRepository->findBy(['statut' => 'en_attente'], ['createdAt' => 'DESC'], 5);
        $pendingDocuments = $documentRepository->findBy(['status' => Document::STATUS_PENDING], ['dateDocument' => 'DESC'], 5);
        $paymentActions = $paiementRepository->findAdminActionRequired(5);
        $paymentActionCount = $paiementRepository->countAdminActionRequired();

        return $this->render('admin/_navbar_summary.html.twig', [
            'pendingReservations' => $pendingReservations,
            'pendingDocuments' => $pendingDocuments,
            'paymentActions' => $paymentActions,
            'paymentActionCount' => $paymentActionCount,
            'pendingCount' => count($pendingReservations) + count($pendingDocuments) + $paymentActionCount,
        ]);
    }
}
