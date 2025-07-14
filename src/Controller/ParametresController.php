<?php

namespace App\Controller;

use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ParametresController extends AbstractController
{
    /**
     * @Route("/user/parametres", name="app_parametres")
     */
    public function parametres(): Response
    {
        return $this->render('user/parametres.html.twig');
    }

    /**
     * @Route("/user/parametres/photo", name="app_photo_update", methods={"POST"})
     */
    public function updatePhoto(Request $request, SluggerInterface $slugger, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if ($request->get('remove_photo') && $user->getPhoto()) {
            $oldPath = $this->getParameter('photos_directory') . '/' . $user->getPhoto();
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
            $user->setPhoto(null);
            $em->flush();
            $this->addFlash('success', 'Votre photo de profil a été supprimée.');
            return $this->redirectToRoute('app_parametres');
        }

        $photoFile = $request->files->get('photo');
        if ($photoFile) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($photoFile->getMimeType(), $allowedMimeTypes)) {
                $this->addFlash('error', 'Format invalide. JPG, PNG ou WebP uniquement.');
                return $this->redirectToRoute('app_parametres');
            }

            if ($photoFile->getSize() > 2 * 1024 * 1024) {
                $this->addFlash('error', 'Image trop lourde. Max 2 Mo.');
                return $this->redirectToRoute('app_parametres');
            }

            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

            try {
                $photoFile->move($this->getParameter('photos_directory'), $newFilename);

                if ($user->getPhoto()) {
                    $oldPath = $this->getParameter('photos_directory') . '/' . $user->getPhoto();
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $user->setPhoto($newFilename);
                $em->flush();

                $this->addFlash('success', 'Photo mise à jour avec succès.');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de l’envoi du fichier.');
            }
        }

        return $this->redirectToRoute('app_parametres');
    }

    /**
     * @Route("/user/parametres/infos", name="app_infos_update", methods={"POST"})
     */
    public function updateInfos(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $user->setPrenom($request->request->get('prenom'));
        $user->setNom($request->request->get('nom'));
        $user->setDateNaissance(new \DateTime($request->request->get('dateNaissance')));
        $user->setTelephone($request->request->get('telephone'));

        $em->flush();
        $this->addFlash('success', 'Informations mises à jour avec succès.');

        return $this->redirectToRoute('app_parametres');
    }

    /**
     * @Route("/user/parametres/password", name="app_password_update", methods={"POST"})
     */
    public function updatePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $oldPassword = $request->request->get('oldPassword');
        $newPassword = $request->request->get('newPassword');
        $confirmPassword = $request->request->get('confirmPassword');

        if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
            $this->addFlash('error', 'Mot de passe actuel incorrect.');
        } elseif ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
        } else {
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $em->flush();
            $this->addFlash('success', 'Mot de passe modifié avec succès.');
        }

        return $this->redirectToRoute('app_parametres');
    }

    /**
     * @Route("/user/parametres/document", name="app_document_add", methods={"POST"})
     */
    public function addDocument(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $type = $request->request->get('type_doc');
        $autre = $request->request->get('autre_doc');
        $fichier = $request->files->get('document');

        if (!$type || ($type === 'autre' && !$autre)) {
            $this->addFlash('error', 'Veuillez spécifier le type de document.');
            return $this->redirectToRoute('app_parametres');
        }

        if (!$fichier) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier à envoyer.');
            return $this->redirectToRoute('app_parametres');
        }

        // Sécurité du type MIME
        $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($fichier->getMimeType(), $allowedMime)) {
            $this->addFlash('error', 'Format de document invalide. Autorisés : PDF, JPG, PNG.');
            return $this->redirectToRoute('app_parametres');
        }

        // Taille max 2 Mo
        if ($fichier->getSize() > 2 * 1024 * 1024) {
            $this->addFlash('error', 'Fichier trop volumineux. 2 Mo max.');
            return $this->redirectToRoute('app_parametres');
        }

        // Nom du fichier
        $originalName = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($originalName);
        $newFilename = $safeName . '-' . uniqid() . '.' . $fichier->guessExtension();

        try {
            $fichier->move($this->getParameter('documents_directory'), $newFilename);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload du document.');
            return $this->redirectToRoute('app_parametres');
        }

        // Création de l'entité Document
        $document = new Document();
        $document->setUser($user);
        $document->setTypeDocument($type === 'autre' && $autre ? $autre : $type);
        $document->setFilenameDocument($newFilename);
        $document->setDateDocument(new \DateTime()); // date automatique
        $document->setStatus(Document::STATUS_PENDING); // défaut

        $em->persist($document);
        $em->flush();

        $this->addFlash('success', 'Document ajouté avec succès.');
        return $this->redirectToRoute('app_parametres');
    }


    /**
     * @Route("/user/parametres/delete", name="app_account_delete", methods={"POST"})
     */
    public function deleteAccount(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();

        // Ajoute une vérification CSRF si tu veux renforcer la sécurité
        if (!$this->isCsrfTokenValid('delete_account', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_account_settings');
        }

        // Anonymisation
        $user->anonymiser(); // Méthode à ajouter dans User.php (voir plus bas)

        // Déconnexion
        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        $em->flush();

        $this->addFlash('info', 'Votre compte a été supprimé (anonymisé).');

        return $this->redirectToRoute('app_home');
    }
}
