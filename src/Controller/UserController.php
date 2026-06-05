<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Trajet;
use App\Entity\User;
use App\Form\DocumentFormType;
use App\Repository\NotesRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Repository\TrajetRepository;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\PaiementService;
use App\Service\DocumentVerificationService;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Utils\DateHelper;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class UserController extends AbstractController
{
    /**
     * @Route("/user", name="app_user", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }

    /**
     * @Route("/u/{id}", name="app_profilePublic", methods={"GET"})
     */
    public function profilePublic(int $id, UserRepository $userRepository, NotesRepository $notesRepo): Response
    {
        $user = $userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        // Date en français
        Carbon::setLocale('fr');
        $dateFr = Carbon::parse($user->getCreatedAt());
        $dateMembre = $dateFr->translatedFormat('F Y');

        $notesRecues = $notesRepo->findBy(['notePour' => $user]);

        // Moyenne
        $moyenne = null;
        if (count($notesRecues) > 0) {
            $total = 0;
            foreach ($notesRecues as $note) {
                $total += $note->getNote();
            }
            $moyenne = round($total / count($notesRecues), 1);
        }

        $estVerifie = $user->isProfilVerifieComplet();

        // autres vérifications
        $verifications = [
            'identite' => $user->hasVerifiedIdentity(),
            'email' => $user->isVerified(),
            'telephone' => $user->hasVerifiedPhone(), // ou false si pas encore actif
        ];

        $verifTotal = count($verifications);
        $verifOk = count(array_filter($verifications));

        $verifPourcentage = round($verifOk / $verifTotal * 100);

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'notesRecues' => $notesRecues,
            'noteMoyenne' => $moyenne,
            'dateMembre' => $dateMembre,
            'estVerifie' => $estVerifie,
            'verifications' => $verifications,
            'verifOk' => $verifOk,
            'verifTotal' => $verifTotal,
            'verifPourcentage' => $verifPourcentage,
        ]);
    }

    /**
     * @Route("/user/profil/{id}", name="app_profile", methods={"GET"})
     */
    public function profile(int $id, UserRepository $userRepository, NotesRepository $notesRepo): Response
    {
        $user = $userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        // Date en français
        Carbon::setLocale('fr');
        $dateFr = Carbon::parse($user->getCreatedAt());
        $dateMembre = $dateFr->translatedFormat('F Y');

        $notesRecues = $notesRepo->findBy(['notePour' => $user]);

        // Moyenne
        $moyenne = null;
        if (count($notesRecues) > 0) {
            $total = 0;
            foreach ($notesRecues as $note) {
                $total += $note->getNote();
            }
            $moyenne = round($total / count($notesRecues), 1);
        }

        $estVerifie = $user->isProfilVerifieComplet();

        // autres vérifications
        $verifications = [
            'identite' => $user->hasVerifiedIdentity(),
            'email' => $user->isVerified(),
            'telephone' => $user->hasVerifiedPhone(), // ou false si pas encore actif
        ];

        $verifTotal = count($verifications);
        $verifOk = count(array_filter($verifications));

        $verifPourcentage = round($verifOk / $verifTotal * 100);

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'notesRecues' => $notesRecues,
            'noteMoyenne' => $moyenne,
            'dateMembre' => $dateMembre,
            'estVerifie' => $estVerifie,
            'verifications' => $verifications,
            'verifOk' => $verifOk,
            'verifTotal' => $verifTotal,
            'verifPourcentage' => $verifPourcentage,
        ]);
    }

    /**
     * @Route("/user/preferences", name="app_preferences_update", methods={"POST"})
     */
    public function updatePreferences(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('profile_preferences_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $preferences = $request->request->all('preferences');
        $user->setPreferences($preferences);
        $em->flush();

        $this->addFlash('success', 'Préférences mises à jour avec succès.');
        return $this->redirectToRoute('app_profile', ['id' => $user->getId()]);
    }

    /**
     * @Route("/user/photo", name="app_photoProfil_update", methods={"POST"})
     */
    public function updatePhoto(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('profile_photo_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($request->get('remove_photo') && $user->getPhoto()) {
            $oldPath = $this->getParameter('photos_directory') . '/' . $user->getPhoto();
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
            $user->setPhoto(null);
            $em->flush();
            $this->addFlash('success', 'Votre photo de profil a été supprimée.');
            return $this->redirectToRoute('app_profile', ['id' => $user->getId()]);
        }

        $photoFile = $request->files->get('photo');
        if ($photoFile) {
            // Vérification des formats autorisés
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($photoFile->getMimeType(), $allowedMimeTypes)) {
                $this->addFlash('error', 'Format invalide. JPG, PNG ou WebP uniquement.');
                return $this->redirectToRoute('app_profile', ['id' => $user->getId()]);
            }

            // Vérification de la taille
            if ($photoFile->getSize() > 2 * 1024 * 1024) {
                $this->addFlash('error', 'Image trop lourde. Max 2 Mo.');
                return $this->redirectToRoute('app_profile', ['id' => $user->getId()]);
            }

            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

            try {
                $photoFile->move($this->getParameter('photos_directory'), $newFilename);

                // Suppression automatique de l’ancienne photo
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

        return $this->redirectToRoute('app_profile', ['id' => $user->getId()]);
    }

    /**
     * @Route("/user/update-description", name="app_update_description", methods={"POST"})
     */
    public function updateDescription(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('profile_description_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $description = trim($request->request->get('description'));

        $user->setDescription($description);
        $em->flush();

        $this->addFlash('success', 'Description mise à jour.');
        return $this->redirectToRoute('app_profile', ['id' => $user->getId()]);
    }

    /**
     * @Route("/user/compte/", name="app_compte", methods={"GET"})
     */
    public function compte(): Response
    {
        return $this->render('user/compte.html.twig', [
            // 'controller_name' => 'UserController',
        ]);
    }

    /**
     * @Route("/user/documents", name="app_documents", methods={"GET", "POST"})
     */
    public function mesDocuments(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        DocumentVerificationService $documentVerificationService
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        // Soumission du formulaire manuel
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('documents_add', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_documents');
            }

            $type = $request->request->get('type_doc');
            $autreType = $request->request->get('autre_doc');
            $file = $request->files->get('document');

            if (!$type || !$file) {
                $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
                return $this->redirectToRoute('app_documents');
            }

            // Déterminer le type réel
            $finalType = ($type === 'Autre' && !empty($autreType)) ? $autreType : $type;

            // Vérification du type MIME
            $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                $this->addFlash('error', 'Format non autorisé. Seuls les PDF, JPG ou PNG sont acceptés.');
                return $this->redirectToRoute('app_documents');
            }

            // Vérification taille
            if ($file->getSize() > 2 * 1024 * 1024) {
                $this->addFlash('error', 'Le fichier dépasse la taille maximale autorisée (2 Mo).');
                return $this->redirectToRoute('app_documents');
            }

            $verification = $documentVerificationService->verify($file, $finalType);
            if (!$verification['valid']) {
                $this->addFlash('error', 'Document refusé par la pré-vérification automatique : ' . $verification['reason']);
                return $this->redirectToRoute('app_documents');
            }

            // Générer un nom de fichier unique
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeName = $slugger->slug($originalName);
            $filename = $safeName . '-' . uniqid() . '.' . $file->guessExtension();

            try {
                $file->move($this->getParameter('documents_directory'), $filename);

                $document = new Document();
                $document->setTypeDocument($finalType);
                $document->setFilenameDocument($filename);
                $document->setDateDocument(new \DateTime());
                $document->setUser($user);
                $document->setStatus(Document::STATUS_APPROVED);

                $em->persist($document);
                $em->flush();

                $this->addFlash('success', 'Document ajouté et validé automatiquement. ' . $verification['reason']);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l’envoi du fichier.');
            }

            return $this->redirectToRoute('app_documents');
        }

        $documents = $em->getRepository(Document::class)->findBy(['user' => $user]);

        return $this->render('user/mes_documents.html.twig', [
            'documents' => $documents,
        ]);
    }



    /**
     * Affiche les trajets publiés par l'utilisateur connecté (conducteur)
     * 
     * @Route("/user/mes-trajets", name="app_mes_trajets", methods={"GET"})
     */
    public function mesTrajets(TrajetRepository $trajetRepository): Response
    {
        $user = $this->getUser();

        // Trajets publiés par l'utilisateur, du plus récent au plus ancien
        $trajets = $trajetRepository->findBy(
            ['conducteur' => $user],
            ['dateTrajet' => 'DESC', 'heureTrajet' => 'DESC']
        );

        return $this->render('user/mes_trajets.html.twig', [
            'trajets' => $trajets,
        ]);
    }

    /**
     * Affiche les réservations faites par l'utilisateur connecté (passager)
     * 
     * @Route("/user/mes-reservations", name="app_mes_reservations", methods={"GET"})
     */
    public function mesReservations(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();

        // Réservations effectuées par l'utilisateur, triées par date de trajet
        $reservations = $reservationRepository->createQueryBuilder('r')
            ->leftJoin('r.trajet', 't')
            ->addSelect('t')
            ->where('r.passager = :user')
            ->setParameter('user', $user)
            ->orderBy('t.dateTrajet', 'DESC')
            ->addOrderBy('t.heureTrajet', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('user/mes_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }


    /**
     * @Route("/user/mes-paiements", name="app_mes_paiements", methods={"GET"})
     */
    public function mesPaiements(PaiementRepository $paiementRepository): Response
    {
        // ⚠️ À compléter plus tard avec les données Stripe ou ton entité Paiement
        $user = $this->getUser();

        $paiements = $paiementRepository->createQueryBuilder('p')
            ->innerJoin('p.reservation', 'r')
            ->addSelect('r')
            ->innerJoin('r.trajet', 't')
            ->addSelect('t')
            ->where('r.passager = :user OR t.conducteur = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('user/mes_paiements.html.twig', [
            'paiements' => $paiements,
        ]);
    }


    /**
     * Affiche le détail d’un trajet pour le conducteur connecté
     *
     * @Route("/user/trajet/{id}", name="app_user_trajet", methods={"GET"})
     */
    public function showTrajet(
        int $id,
        TrajetRepository $trajetRepository,
        ReservationRepository $reservationRepository
    ): Response {
        // Récupération du trajet par son ID
        $trajet = $trajetRepository->find($id);

        // Si le trajet n'existe pas, on renvoie une erreur 404
        if (!$trajet) {
            throw $this->createNotFoundException('Trajet introuvable.');
        }

        // Vérifie que l’utilisateur connecté est bien le conducteur du trajet
        if ($trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à effectuer cette action.");
        }

        // Récupère les réservations liées à ce trajet, triées par ID décroissant
        $reservations = $reservationRepository->findBy(
            ['trajet' => $trajet],
            ['id' => 'DESC']
        );

        // Initialisation des indicateurs de statut
        $datePasse = false; // Le trajet est-il dans le passé ?
        $enCours = false;   // Le trajet est-il en cours actuellement ?

        // Création de l'objet DateTime combinant la date et l'heure du trajet
        $datetimeTrajet = \DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i:s')
        );

        // Date et heure actuelles
        $maintenant = new \DateTime();

        // Le trajet est considéré comme "passé" si sa date+heure sont inférieures à maintenant
        if ($maintenant > $datetimeTrajet) {
            $datePasse = true;
        }

        // Le trajet est considéré comme "en cours" si :
        // - il a lieu aujourd’hui
        // - l’heure actuelle est entre l’heure de départ et +6 heures après le départ
        if (
            $trajet->getDateTrajet()->format('Y-m-d') === $maintenant->format('Y-m-d') &&
            $datetimeTrajet <= $maintenant &&
            $maintenant < (clone $datetimeTrajet)->modify('+6 hours')
        ) {
            $enCours = true;
        }

        $ladateTrajet = DateHelper::formatDateFr($trajet->getDateTrajet(), 'l d F Y');


        // Affichage du template avec les variables nécessaires
        return $this->render('user/mon_trajet.html.twig', [
            'trajet' => $trajet,
            'reservations' => $reservations,
            'datePasse' => $datePasse,
            'enCours' => $enCours,
            'ladateTrajet' => $ladateTrajet
        ]);
    }

    /**
     * @Route("/user/reservation/{id}", name="app_user_reservation", methods={"GET"})
     */
    public function showReservation(
        int $id,
        ReservationRepository $reservationRepository
    ): Response {
        $reservation = $reservationRepository->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        // Vérifie que le passager connecté est bien le propriétaire de la réservation
        if ($reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas accès à cette réservation.");
        }

        $trajet = $reservation->getTrajet();

        $ladateTrajet = DateHelper::formatDateFr($trajet->getDateTrajet(), 'l d F Y');

        return $this->render('user/ma_reservation.html.twig', [
            'reservation' => $reservation,
            'trajet' => $trajet,
            'ladateTrajet' => $ladateTrajet,
        ]);
    }

    /**
     * @Route("/user/reservation/{id}/annuler", name="reservation_annuler", methods={"POST"})
     */
    public function annuler(
        int $id,
        Request $request,
        ReservationRepository $repo,
        PaiementService $paiement,
        EntityManagerInterface $em
    ): Response {
        $reservation = $repo->find($id);

        // 🛑 Vérifie que c’est bien le passager qui annule
        if (!$reservation || $reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // 🔐 Vérifie le token CSRF
        if (!$this->isCsrfTokenValid('annuler_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // ➕ On ne supprime pas la réservation : on la marque comme annulée
        if (!in_array($reservation->getStatut(), ['en_attente', 'acceptee', 'payee'], true)) {
            $this->addFlash('info', 'Cette réservation ne peut plus être annulée.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        $trajet = $reservation->getTrajet();
        $trajetDateTime = new \DateTimeImmutable($trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i'));
        if ($trajetDateTime < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Ce trajet est déjà passé, la réservation ne peut plus être annulée.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        $paiementReservation = $reservation->getPaiement();
        if ($paiementReservation && $paiementReservation->getStatut() === 'capture') {
            $paiement->rembourserSelonPolitique($reservation, false);
        } else {
            $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());
        }

        $reservation->setStatut('annulee');

        // 💸 Remboursement partiel ou total → à gérer dans l'étape B
        $em->flush();

        $this->addFlash('info', 'Réservation annulée.');
        return $this->redirectToRoute('app_mes_reservations');
    }

    /**
     * @Route("/user/profil/email/renvoyer", name="app_user_resend_confirmation", methods={"POST"})
     */
    public function resendEmailConfirmation(
        Request $request,
        EmailVerifier $emailVerifier,
        VerifyEmailHelperInterface $verifyEmailHelper,
        MailerInterface $mailer
    ): RedirectResponse {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('resend_confirmation_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Votre adresse e-mail est déjà vérifiée.');
            return $this->redirectToRoute('app_profile', ['id' => $user->getId()]);
        }

        try {
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

            $this->addFlash('success', '📤 Un nouveau lien de confirmation vous a été envoyé à '.$user->getEmail().'.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_profile', ['id' => $user->getId()]);
    }

}
