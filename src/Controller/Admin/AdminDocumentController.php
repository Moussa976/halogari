<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class AdminDocumentController extends AbstractController
{
    /**
     * Valider un document
     * @Route("/admin/document/{id}/valider", name="admin_document_validate", methods={"POST"})
     */
    public function validate(Document $document, EntityManagerInterface $em): RedirectResponse
    {
        $document->setStatus(Document::STATUS_APPROVED);
        $em->flush();

        $this->addFlash('success', 'Document validé avec succès.');

        return $this->redirectToRoute('admin_user_show', ['id' => $document->getUser()->getId()]);
    }

    /**
     * Refuser un document
     * @Route("/admin/document/{id}/refuser", name="admin_document_reject", methods={"POST"})
     */
    public function reject(Document $document, EntityManagerInterface $em): RedirectResponse
    {
        $document->setStatus(Document::STATUS_REJECTED);
        $em->flush();

        $this->addFlash('warning', 'Document refusé.');

        return $this->redirectToRoute('admin_user_show', ['id' => $document->getUser()->getId()]);
    }

    /**
     * Remettre un document en attente (pending)
     * @Route("/admin/document/{id}/en-attente", name="admin_document_pending", methods={"POST"})
     */
    public function setPending(Document $document, EntityManagerInterface $em): RedirectResponse
    {
        $document->setStatus(Document::STATUS_PENDING);
        $em->flush();

        $this->addFlash('info', 'Le document a été remis en attente.');

        return $this->redirectToRoute('admin_user_show', [
            'id' => $document->getUser()->getId(),
        ]);
    }
}
