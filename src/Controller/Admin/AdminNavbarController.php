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
        $capturedPayments = $paiementRepository->findBy(['statut' => 'capture'], ['createdAt' => 'DESC'], 5);

        return $this->render('admin/_navbar_summary.html.twig', [
            'pendingReservations' => $pendingReservations,
            'pendingDocuments' => $pendingDocuments,
            'capturedPayments' => $capturedPayments,
            'pendingCount' => count($pendingReservations) + count($pendingDocuments) + count($capturedPayments),
        ]);
    }
}
