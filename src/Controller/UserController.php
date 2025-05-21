<?php

namespace App\Controller;

use App\Entity\Document;
use App\Form\DocumentFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class UserController extends AbstractController
{
    /**
     * @Route("/user", name="app_user")
     */
    public function index(): Response
    {
        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }

    /**
     * @Route("/user/profil/", name="app_profile")
     */
    public function profile(): Response
    {
        return $this->render('user/profile.html.twig', [
            // 'controller_name' => 'UserController',
        ]);
    }

    /**
     * @Route("/user/compte/", name="app_compte")
     */
    public function compte(): Response
    {
        return $this->render('user/compte.html.twig', [
            // 'controller_name' => 'UserController',
        ]);
    }

    /**
     * @Route("/user/documents/", name="app_documents")
     */
    public function mesDocuments(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        SessionInterface $session
    ): Response {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $session->set('_security.main.target_path', $request->getUri());
            $this->addFlash('error', 'Vous devez être connecté pour voir vos documents.');
            return $this->redirectToRoute('app_login');
        }
        $user = $this->getUser();
        $document = new Document();
        $form = $this->createForm(DocumentFormType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('file')->getData();

            if ($uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                try {
                    $uploadedFile->move(
                        $this->getParameter('documents_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l’envoi du fichier.');
                    return $this->redirectToRoute('app_documents');
                }

                $document->setFilenameDocument($newFilename);
                $document->setDateDocument(new \DateTime());
                $document->setUser($user);

                $em->persist($document);
                $em->flush();

                $this->addFlash('success', 'Document ajouté avec succès.');
                return $this->redirectToRoute('app_documents');
            }
        }

        $documents = $em->getRepository(Document::class)->findBy(['user' => $user]);

        return $this->render('user/mes_documents.html.twig', [
            'documentForm' => $form->createView(),
            'documents' => $documents,
        ]);
    }
}
