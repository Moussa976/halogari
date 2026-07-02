<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\AdminSeenAction;
use App\Entity\User;
use App\Repository\AdminSeenActionRepository;
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
        PaiementRepository $paiementRepository,
        AdminSeenActionRepository $seenActionRepository
    ): Response {
        $admin = $this->getUser();
        $pendingReservationsAll = $reservationRepository->findBy(['statut' => 'en_attente'], ['createdAt' => 'DESC']);
        $pendingDocumentsAll = $documentRepository->findBy(['status' => Document::STATUS_PENDING], ['dateDocument' => 'DESC']);
        $paymentActionsAll = $paiementRepository->findAdminActionRequired(PHP_INT_MAX);

        $pendingReservations = array_slice($pendingReservationsAll, 0, 5);
        $pendingDocuments = array_slice($pendingDocumentsAll, 0, 5);
        $paymentActions = array_slice($paymentActionsAll, 0, 5);

        $reservationIds = array_map(static fn ($reservation): int => (int) $reservation->getId(), $pendingReservationsAll);
        $documentIds = array_map(static fn (Document $document): int => (int) $document->getId(), $pendingDocumentsAll);
        $paymentIds = array_map(static fn ($paiement): int => (int) $paiement->getId(), $paymentActionsAll);

        $reservationSeen = [];
        $documentSeen = [];
        $paymentSeen = [];
        $pendingUnreadCount = count($reservationIds) + count($documentIds) + count($paymentIds);

        if ($admin instanceof User) {
            $reservationSeen = $seenActionRepository->seenMap($admin, AdminSeenAction::TYPE_RESERVATION, $reservationIds);
            $documentSeen = $seenActionRepository->seenMap($admin, AdminSeenAction::TYPE_DOCUMENT, $documentIds);
            $paymentSeen = $seenActionRepository->seenMap($admin, AdminSeenAction::TYPE_PAIEMENT, $paymentIds);
            $pendingUnreadCount = $seenActionRepository->countUnseen($admin, AdminSeenAction::TYPE_RESERVATION, $reservationIds)
                + $seenActionRepository->countUnseen($admin, AdminSeenAction::TYPE_DOCUMENT, $documentIds)
                + $seenActionRepository->countUnseen($admin, AdminSeenAction::TYPE_PAIEMENT, $paymentIds);
        }

        return $this->render('admin/_navbar_summary.html.twig', [
            'pendingReservations' => $pendingReservations,
            'pendingReservationCount' => count($pendingReservationsAll),
            'reservationSeen' => $reservationSeen,
            'pendingDocuments' => $pendingDocuments,
            'pendingDocumentCount' => count($pendingDocumentsAll),
            'documentSeen' => $documentSeen,
            'paymentActions' => $paymentActions,
            'paymentActionCount' => count($paymentActionsAll),
            'paymentSeen' => $paymentSeen,
            'pendingCount' => $pendingUnreadCount,
        ]);
    }
}
