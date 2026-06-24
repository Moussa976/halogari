<?php

namespace App\Controller\Admin;

use App\Entity\Notes;
use App\Entity\User;
use App\Repository\NotesRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminNoteController extends AbstractController
{
    /**
     * @Route("/admin/notes", name="admin_notes", methods={"GET"})
     */
    public function index(NotesRepository $notesRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/notes/index.html.twig', [
            'notes' => $notesRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    /**
     * @Route("/admin/notes/{id}/supprimer", name="admin_note_delete", requirements={"id"="\d+"}, methods={"POST"})
     */
    public function delete(Notes $note, Request $request, EntityManagerInterface $em, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if (!$this->isCsrfTokenValid('admin_note_delete_' . $note->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
        }

        $targetUser = $note->getNotePour();
        $auditLogger->log(
            $this->getUser() instanceof User ? $this->getUser() : null,
            'note_delete',
            $targetUser,
            [
                'noteId' => $note->getId(),
                'note' => $note->getNote(),
                'noteurId' => $note->getNoteur() ? $note->getNoteur()->getId() : null,
                'notePourId' => $targetUser ? $targetUser->getId() : null,
                'trajetId' => $note->getTrajet() ? $note->getTrajet()->getId() : null,
            ]
        );

        $em->remove($note);
        $em->flush();

        $this->addFlash('success', 'La note a été supprimée.');

        return $this->redirectToRoute('admin_notes');
    }
}
