<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class VerificationController extends AbstractController
{
    /**
     * @Route("/verification", name="app_verification")
     */
    public function verification(Request $request, EntityManagerInterface $em, Security $security): Response
    {
        $user = $security->getUser();

        if ($request->isMethod('POST')) {
            $permis = $request->files->get('permis');
            $identite = $request->files->get('identite');
            $photo = $request->files->get('photo');

            $dossier = $this->getParameter('documents_directory') . '/conducteurs/' . $user->getId();
            if (!file_exists($dossier)) {
                mkdir($dossier, 0775, true);
            }

            if ($permis) {
                $permis->move($dossier, 'permis.pdf');
            }

            if ($identite) {
                $identite->move($dossier, 'identite.pdf');
            }

            if ($photo) {
                $photo->move($dossier, 'photo.jpg');
            }

            $this->addFlash('success', 'Vos documents ont bien été envoyés. Un membre de l’association les examinera bientôt.');

            return $this->redirectToRoute('mon_compte');
        }

        return $this->render('verification/verification.html.twig');
    }
}
