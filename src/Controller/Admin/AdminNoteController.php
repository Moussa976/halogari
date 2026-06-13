<?php

namespace App\Controller\Admin;

use App\Repository\NotesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
