<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Service\AdminNotificationMailer;
use App\Service\DocumentStorage;
use App\Service\DocumentVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ParametresController extends AbstractController
{
    /**
     * @Route("/user/parametres", name="app_parametres", methods={"GET"})
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

        if (!$this->isCsrfTokenValid('parametres_photo', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La session a expiré. Veuillez réessayer.');
            return $this->redirectToRoute('app_parametres');
        }

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
                $this->addFlash('error', "Erreur lors de l'envoi du fichier.");
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

        if (!$this->isCsrfTokenValid('parametres_infos', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La session a expiré. Veuillez réessayer.');
            return $this->redirectToRoute('app_parametres');
        }

        $user->setPrenom($request->request->get('prenom'));
        $user->setNom($request->request->get('nom'));
        $dateNaissance = $this->parseFrenchDate((string) $request->request->get('dateNaissance'));
        if (!$dateNaissance) {
            $this->addFlash('error', 'La date de naissance doit être au format jj/mm/aaaa.');
            return $this->redirectToRoute('app_parametres');
        }

        $user->setDateNaissance($dateNaissance);
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

        if (!$this->isCsrfTokenValid('parametres_password', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La session a expiré. Veuillez réessayer.');
            return $this->redirectToRoute('app_parametres');
        }

        $oldPassword = $request->request->get('oldPassword');
        $newPassword = $request->request->get('newPassword');
        $confirmPassword = $request->request->get('confirmPassword');

        if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
            $this->addFlash('error', 'Mot de passe actuel incorrect.');
        } elseif ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', (string) $newPassword)) {
            $this->addFlash('error', 'Votre nouveau mot de passe doit contenir au moins 8 caractères, une minuscule, une majuscule, un chiffre et un caractère spécial, par exemple : Mayotte@2026.');
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
    public function addDocument(
        Request $request,
        EntityManagerInterface $em,
        DocumentVerificationService $documentVerificationService,
        AdminNotificationMailer $adminNotificationMailer,
        DocumentStorage $documentStorage
    ): Response
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

        $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($fichier->getMimeType(), $allowedMime)) {
            $this->addFlash('error', 'Format de document invalide. Autorisés : PDF, JPG, PNG.');
            return $this->redirectToRoute('app_parametres');
        }

        if ($fichier->getSize() > 2 * 1024 * 1024) {
            $this->addFlash('error', 'Fichier trop volumineux. 2 Mo max.');
            return $this->redirectToRoute('app_parametres');
        }

        $finalType = $type === 'autre' && $autre ? $autre : $type;
        $verification = $documentVerificationService->verify($fichier, $finalType);
        if (!$verification['valid']) {
            $this->addFlash('error', 'Document refusé par la pré-vérification automatique : ' . $verification['reason']);
            return $this->redirectToRoute('app_parametres');
        }

        $originalFilename = $fichier->getClientOriginalName();
        $mimeType = $fichier->getMimeType();
        $fileSize = $fichier->getSize();

        try {
            $newFilename = $documentStorage->store($fichier, $user->getId());
        } catch (\Throwable $e) {
            $this->addFlash('error', "Erreur lors du stockage du document. Merci de réessayer avec un nom de fichier simple.");
            return $this->redirectToRoute('app_parametres');
        }

        $document = new Document();
        $document->setUser($user);
        $document->setTypeDocument($finalType);
        $document->setFilenameDocument($newFilename);
        $document->setOriginalFilename($originalFilename);
        $document->setMimeType($mimeType);
        $document->setFileSize($fileSize);
        $document->setDateDocument(new \DateTime());
        $document->setStatus(Document::STATUS_PENDING);

        $em->persist($document);
        $em->flush();

        $adminNotificationMailer->notify(
            'Document utilisateur reçu',
            sprintf(
                "%s %s <%s> a envoyé un document %s. Il attend une validation admin.",
                $user->getPrenom(),
                $user->getNom(),
                $user->getEmail(),
                $finalType
            ),
            '/admin/documents'
        );

        $this->addFlash('success', 'Document envoyé. Il est maintenant en attente de validation par l’administration.');
        return $this->redirectToRoute('app_parametres');
    }

    /**
     * @Route("/user/parametres/delete", name="app_account_delete", methods={"POST"})
     */
    public function deleteAccount(
        Request $request,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('parametres_document', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La session a expiré. Veuillez réessayer.');
            return $this->redirectToRoute('app_parametres');
        }

        if (!$this->isCsrfTokenValid('delete_account', $request->request->get('_token'))) {
            $this->addFlash('error', 'La session a expiré. Veuillez réessayer.');
            return $this->redirectToRoute('app_parametres');
        }

        $deletePassword = (string) $request->request->get('deletePassword', '');
        if (!$passwordHasher->isPasswordValid($user, $deletePassword)) {
            $this->addFlash('error', 'Mot de passe incorrect. La suppression du compte a été annulée.');
            return $this->redirectToRoute('app_parametres');
        }

        $email = $user->getEmail();
        $prenom = $user->getPrenom();
        $deletedAt = new \DateTimeImmutable();

        $user->anonymiser();
        $em->flush();

        try {
            $message = (new TemplatedEmail())
                ->from(new Address('moussa@halogari.yt', 'HaloGari'))
                ->to($email)
                ->subject('Confirmation de suppression de votre compte HaloGari')
                ->htmlTemplate('emails/account_deleted.html.twig')
                ->context([
                    'prenom' => $prenom,
                    'deletedAt' => $deletedAt,
                ])
                ->embedFromPath($this->getParameter('kernel.project_dir') . '/public/images/logo.png', 'logo_halogari');

            $mailer->send($message);
        } catch (\Throwable $exception) {
            // Le compte est déjà anonymisé : on évite de bloquer la suppression si l'e-mail échoue.
        }

        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();
        $this->addFlash('info', 'Votre compte a été supprimé. Un e-mail de confirmation vous a été envoyé.');

        return $this->redirectToRoute('app_home');
    }

    private function parseFrenchDate(string $date): ?\DateTime
    {
        $date = trim($date);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            $parsed = \DateTime::createFromFormat('!' . $format, $date);
            if ($parsed && $parsed->format($format) === $date) {
                return $parsed;
            }
        }

        return null;
    }
}

