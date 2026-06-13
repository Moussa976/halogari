<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function validate(Document $document, Request $request, EntityManagerInterface $em, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $this->assertValidDocumentToken($document, $request, 'validate');

        $document->setStatus(Document::STATUS_APPROVED);
        $em->flush();
        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'document_validate', $document->getUser(), ['documentId' => $document->getId(), 'type' => $document->getTypeDocument()]);

        $this->addFlash('success', 'Document validé avec succès.');

        return $this->redirectToRoute('admin_user_show', ['id' => $document->getUser()->getId()]);
    }

    /**
     * Refuser un document
     * @Route("/admin/document/{id}/refuser", name="admin_document_reject", methods={"POST"})
     */
    public function reject(Document $document, Request $request, EntityManagerInterface $em, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $this->assertValidDocumentToken($document, $request, 'reject');

        $document->setStatus(Document::STATUS_REJECTED);
        $em->flush();
        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'document_reject', $document->getUser(), ['documentId' => $document->getId(), 'type' => $document->getTypeDocument()]);

        $this->addFlash('warning', 'Document refusé.');

        return $this->redirectToRoute('admin_user_show', ['id' => $document->getUser()->getId()]);
    }

    /**
     * Remettre un document en attente (pending)
     * @Route("/admin/document/{id}/en-attente", name="admin_document_pending", methods={"POST"})
     */
    public function setPending(Document $document, Request $request, EntityManagerInterface $em, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $this->assertValidDocumentToken($document, $request, 'pending');

        $document->setStatus(Document::STATUS_PENDING);
        $em->flush();
        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'document_pending', $document->getUser(), ['documentId' => $document->getId(), 'type' => $document->getTypeDocument()]);

        $this->addFlash('info', 'Le document a été remis en attente.');

        return $this->redirectToRoute('admin_user_show', [
            'id' => $document->getUser()->getId(),
        ]);
    }

    private function assertValidDocumentToken(Document $document, Request $request, string $action): void
    {
        if (!$this->isCsrfTokenValid('admin_document_' . $action . '_' . $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
        }
    }
}

