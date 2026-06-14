<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Service\AdminAuditLogger;
use App\Service\DocumentDecisionNotifier;
use App\Service\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class AdminDocumentController extends AbstractController
{
    /**
     * @Route("/admin/documents", name="admin_documents", methods={"GET"})
     */
    public function index(DocumentRepository $documentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/documents/index.html.twig', [
            'documents' => $documentRepository->findBy([], ['dateDocument' => 'DESC']),
        ]);
    }

    /**
     * Valider un document
     * @Route("/admin/document/{id}/valider", name="admin_document_validate", methods={"POST"})
     */
    public function validate(Document $document, Request $request, EntityManagerInterface $em, AdminAuditLogger $auditLogger, DocumentDecisionNotifier $documentDecisionNotifier): RedirectResponse
    {
        $this->assertValidDocumentToken($document, $request, 'validate');

        $document->setStatus(Document::STATUS_APPROVED);
        $document->setRejectionReason(null);
        $document->setReviewedAt(new \DateTime());
        $document->setReviewedBy($this->getUser() instanceof User ? $this->getUser() : null);
        $em->flush();
        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'document_validate', $document->getUser(), ['documentId' => $document->getId(), 'type' => $document->getTypeDocument()]);
        $this->notifyUser($documentDecisionNotifier, $document, Document::STATUS_APPROVED);

        $this->addFlash('success', 'Document validé avec succès.');

        return $this->redirectAfterAction($document, $request);
    }

    /**
     * Refuser un document
     * @Route("/admin/document/{id}/refuser", name="admin_document_reject", methods={"POST"})
     */
    public function reject(Document $document, Request $request, EntityManagerInterface $em, AdminAuditLogger $auditLogger, DocumentDecisionNotifier $documentDecisionNotifier): RedirectResponse
    {
        $this->assertValidDocumentToken($document, $request, 'reject');
        $reason = trim((string) $request->request->get('reason'));
        if ($reason === '') {
            $this->addFlash('error', 'Merci d’indiquer un motif de refus.');
            return $this->redirectAfterAction($document, $request);
        }

        $document->setStatus(Document::STATUS_REJECTED);
        $document->setRejectionReason($reason);
        $document->setReviewedAt(new \DateTime());
        $document->setReviewedBy($this->getUser() instanceof User ? $this->getUser() : null);
        $em->flush();
        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'document_reject', $document->getUser(), ['documentId' => $document->getId(), 'type' => $document->getTypeDocument(), 'reason' => $reason]);
        $this->notifyUser($documentDecisionNotifier, $document, Document::STATUS_REJECTED);

        $this->addFlash('warning', 'Document refusé.');

        return $this->redirectAfterAction($document, $request);
    }

    /**
     * Remettre un document en attente (pending)
     * @Route("/admin/document/{id}/en-attente", name="admin_document_pending", methods={"POST"})
     */
    public function setPending(Document $document, Request $request, EntityManagerInterface $em, AdminAuditLogger $auditLogger, DocumentDecisionNotifier $documentDecisionNotifier): RedirectResponse
    {
        $this->assertValidDocumentToken($document, $request, 'pending');

        $document->setStatus(Document::STATUS_PENDING);
        $document->setRejectionReason(null);
        $document->setReviewedAt(null);
        $document->setReviewedBy(null);
        $em->flush();
        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'document_pending', $document->getUser(), ['documentId' => $document->getId(), 'type' => $document->getTypeDocument()]);
        $this->notifyUser($documentDecisionNotifier, $document, Document::STATUS_PENDING);

        $this->addFlash('info', 'Le document a été remis en attente.');

        return $this->redirectAfterAction($document, $request);
    }

    /**
     * @Route("/admin/document/{id}/fichier", name="admin_document_file", methods={"GET"})
     */
    public function documentFile(Document $document, DocumentStorage $documentStorage, AdminAuditLogger $auditLogger): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $path = $documentStorage->resolvePath($document);
        if (!$path) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'document_open', $document->getUser(), ['documentId' => $document->getId(), 'type' => $document->getTypeDocument()]);

        $response = new BinaryFileResponse($path);
        if ($document->getMimeType()) {
            $response->headers->set('Content-Type', $document->getMimeType());
        }
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $document->getOriginalFilename() ?: basename($path)
        );

        return $response;
    }

    private function assertValidDocumentToken(Document $document, Request $request, string $action): void
    {
        if (!$this->isCsrfTokenValid('admin_document_' . $action . '_' . $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
        }
    }

    private function redirectAfterAction(Document $document, Request $request): RedirectResponse
    {
        $returnTo = (string) $request->request->get('_return_to');
        if ($returnTo === 'documents') {
            return $this->redirectToRoute('admin_documents');
        }

        if ($document->getUser()) {
            return $this->redirectToRoute('admin_user_show', ['id' => $document->getUser()->getId()]);
        }

        return $this->redirectToRoute('admin_documents');
    }

    private function notifyUser(DocumentDecisionNotifier $notifier, Document $document, string $decision): void
    {
        try {
            $notifier->notify($document, $decision);
        } catch (\Throwable $exception) {
            $this->addFlash('warning', 'La décision a été enregistrée, mais l’e-mail utilisateur n’a pas pu être envoyé.');
        }
    }
}

