<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\StripeConnectService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class AdminUserController extends AbstractController
{
    /**
     * Affiche la liste des utilisateurs
     * @Route("/admin/utilisateurs", name="admin_users")
     */
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * Affiche le profil complet d'un utilisateur
     * @Route("/admin/utilisateurs/{id}", name="admin_user_show", requirements={"id"="\d+"})
     */
    public function show(User $user, StripeConnectService $stripeConnectService): Response
    {
        $stripeStatus = $stripeConnectService->getStatutCompte($user);

        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
            'stripeStatus' => $stripeStatus
        ]);
    }

    /**
     * @Route("/admin/utilisateurs/{id}/modifier", name="admin_user_update", methods={"POST"})
     */
    public function update(User $user, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): RedirectResponse
    {

        $errors = [];

        // Nettoyage
        $email = trim($request->request->get('email'));
        $nom = trim($request->request->get('nom'));
        $prenom = trim($request->request->get('prenom'));
        $telephone = trim($request->request->get('telephone'));

        // Vérification des champs
        if (!$nom) {
            $errors[] = "Le nom est obligatoire.";
        }
        if (!$prenom) {
            $errors[] = "Le prénom est obligatoire.";
        }
        if (!$telephone) {
            $errors[] = "Le téléphone est obligatoire.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'adresse e-mail n'est pas valide.";
        }

        // Email déjà utilisé par un autre utilisateur
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing && $existing !== $user) {
            $errors[] = "Cette adresse e-mail est déjà utilisée.";
        }

        if ($errors) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        }

        // Si tout est bon : mise à jour // Récupération des données du formulaire
        $user->setNom($request->request->get('nom'));
        $user->setPrenom($request->request->get('prenom'));
        $user->setEmail($request->request->get('email'));
        $user->setTelephone($request->request->get('telephone'));

        // 🔴 Suppression photo si demandé
        if ($request->get('remove_photo') && $user->getPhoto()) {
            $oldPath = $this->getParameter('photos_directory') . '/' . $user->getPhoto();
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
            $user->setPhoto(null);
            $em->flush();
            $this->addFlash('success', 'Photo supprimée avec succès.');
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        }

        // 📤 Upload nouvelle photo si envoyée
        $photoFile = $request->files->get('photo');

        if ($photoFile) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (!in_array($photoFile->getMimeType(), $allowedMimeTypes)) {
                $this->addFlash('error', 'Format invalide. JPG, PNG ou WebP uniquement.');
                return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
            }

            if ($photoFile->getSize() > 2 * 1024 * 1024) {
                $this->addFlash('error', 'Image trop lourde. Max 2 Mo.');
                return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
            }

            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

            try {
                $photoFile->move($this->getParameter('photos_directory'), $newFilename);

                // Suppression de l'ancienne photo
                if ($user->getPhoto()) {
                    $oldPath = $this->getParameter('photos_directory') . '/' . $user->getPhoto();
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $user->setPhoto($newFilename);

                $this->addFlash('success', 'Photo mise à jour avec succès.');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de l’envoi du fichier.');
            }
        }

        // Enregistrement
        $em->flush();

        $this->addFlash('success', 'Informations utilisateur mises à jour avec succès.');
        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    /**
     * Anonymise (supprime) le compte utilisateur
     * @Route("/admin/utilisateurs/{id}/supprimer", name="admin_user_delete", methods={"POST"})
     */
    public function delete(User $user, EntityManagerInterface $em): RedirectResponse
    {
        // Protection contre suppression accidentelle de soi-même
        if ($this->getUser() === $user) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte depuis l’admin.');
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        }

        // Appel à la méthode d’anonymisation
        $user->anonymiser();

        // Sauvegarde
        $em->flush();

        $this->addFlash('success', 'Le compte a été supprimé (anonymisé) avec succès.');

        return $this->redirectToRoute('admin_users');
    }

    /**
     * Donne le rôle ADMIN à l'utilisateur
     * @Route("/admin/utilisateurs/{id}/promouvoir", name="admin_user_promote", methods={"POST"})
     */
    public function promote(User $user, EntityManagerInterface $em): RedirectResponse
    {
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->addFlash('success', 'Utilisateur promu au rôle ADMIN.');
        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }



    /**
     * Crée un compte Stripe Connect avec les infos fournies manuellement
     * @Route("/admin/utilisateurs/{id}/stripe-connect", name="admin_user_create_stripe_custom", methods={"POST"})
     */
    public function createStripeCustom(
        User $user,
        Request $request,
        StripeConnectService $stripeService,
        EntityManagerInterface $em
    ): RedirectResponse {
        // Récupération des données POST
        $nomComplet = $request->request->get('nom_complet');
        $iban = $request->request->get('iban');
        $telephone = $request->request->get('telephone');
        $siteWeb = $request->request->get('site_web');
        $secteur = $request->request->get('secteur');

        $adresse = [
            'line1' => $request->request->get('line1'),
            'city' => $request->request->get('city'),
            'postal_code' => $request->request->get('postal_code'),
            'country' => $request->request->get('country'),
        ];

        try {
            $stripeService->creerCompteAvecRIB($user, $adresse, $iban, $nomComplet, $telephone, $secteur, $siteWeb);
            // Mise à jour des champs supplémentaires dans User si tu veux les conserver
            $user->setTelephone($telephone);
            $em->flush();

            $this->addFlash('success', '✅ Compte Stripe Connect créé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    /**
     * Supprime (ferme) le compte Stripe d’un utilisateur
     * @Route("/admin/utilisateurs/{id}/stripe-supprimer", name="admin_user_delete_stripe", methods={"POST"})
     */
    public function deleteStripe(User $user, StripeConnectService $stripeConnectService): RedirectResponse
    {
        try {
            $stripeConnectService->supprimerCompteStripe($user);
            $this->addFlash('success', '🚫 Compte Stripe supprimé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression du compte Stripe : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    /**
     * Envoie la pièce d'identité à Stripe
     * @Route("/admin/utilisateurs/{id}/envoyer-identite-stripe", name="admin_user_stripe_upload_identity", methods={"POST"})
     */
    public function envoyerIdentiteStripe(User $user, StripeConnectService $stripeService): RedirectResponse
    {
        // Récupération du document de type "identite"
        $doc = $user->getDocumentByType('identite');

        if (!$doc || $doc->getStatus() !== 'approved') {
            $this->addFlash('error', 'Aucun document d’identité validé n’a été trouvé pour cet utilisateur.');
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        }

        $cheminFichier = $this->getParameter('documents_directory') . '/' . $doc->getFilenameDocument();

        try {
            $stripeService->ajouterPieceIdentite($user, $cheminFichier);
            $this->addFlash('success', '✅ Pièce d’identité envoyée avec succès à Stripe.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de la pièce d\'identité : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    /**
     * Renvoyer l’e-mail de confirmation à l’utilisateur
     * @Route("/admin/utilisateurs/{id}/resend-confirmation", name="admin_user_resend_confirmation", methods={"POST"})
     */
    public function resendConfirmation(
    User $user,
    EmailVerifier $emailVerifier, // service déjà utilisé dans Register
    VerifyEmailHelperInterface $verifyEmailHelper,
    MailerInterface $mailer
): RedirectResponse {
    if ($user->isVerified()) {
        $this->addFlash('info', 'Ce compte est déjà vérifié.');
        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    try {
        // Renvoi du mail de confirmation
        $emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            (new TemplatedEmail())
                ->from(new Address('moussa@halogari.yt', 'HaloGari'))
                ->to($user->getEmail())
                ->subject('Veuillez confirmer votre adresse e-mail')
                ->htmlTemplate('emails/confirmation_register.html.twig')
                ->embedFromPath($this->getParameter('kernel.project_dir') . '/public/images/logo.png', 'logo_halogari')
        );

        $this->addFlash('success', "📤 L’e-mail de confirmation a été renvoyé à {$user->getEmail()}.");
    } catch (\Exception $e) {
        $this->addFlash('error', "Erreur lors de l’envoi de l’e-mail : " . $e->getMessage());
    }

    return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
}

}
