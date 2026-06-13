<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Service\DocumentStorage;
use App\Service\DocumentVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VerificationController extends AbstractController
{
    /**
     * @Route("/verification", name="app_verification", methods={"GET", "POST"})
     */
    public function verification(
        Request $request,
        EntityManagerInterface $em,
        DocumentStorage $documentStorage,
        DocumentVerificationService $documentVerificationService
    ): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $uploads = [
                'identite' => $request->files->get('identite'),
                'permis' => $request->files->get('permis'),
            ];

            foreach ($uploads as $type => $file) {
                if (!$file) {
                    continue;
                }

                $verification = $documentVerificationService->verify($file, $type);
                if (!$verification['valid']) {
                    $this->addFlash('error', sprintf('%s : %s', ucfirst($type), $verification['reason']));
                    continue;
                }

                $filename = $documentStorage->store($file, $user->getId());
                $document = (new Document())
                    ->setUser($user)
                    ->setTypeDocument($type)
                    ->setFilenameDocument($filename)
                    ->setOriginalFilename($file->getClientOriginalName())
                    ->setMimeType($file->getMimeType())
                    ->setFileSize($file->getSize())
                    ->setDateDocument(new \DateTime())
                    ->setStatus(Document::STATUS_PENDING);

                $em->persist($document);
            }

            $em->flush();
            $this->addFlash('success', 'Vos documents ont bien été envoyés. Un membre de l’administration les examinera bientôt.');

            return $this->redirectToRoute('app_documents');
        }

        return $this->render('verification/verification.html.twig');
    }
}
