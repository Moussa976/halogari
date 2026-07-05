<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Reservation;
use App\Entity\Trajet;
use App\Entity\User;
use App\Form\DocumentFormType;
use App\Repository\NotesRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Repository\TrajetRepository;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\AdminNotificationMailer;
use App\Service\AfficheService;
use App\Service\CancellationCommunicationService;
use App\Service\PaiementService;
use App\Service\DocumentVerificationService;
use App\Service\DocumentStorage;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
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
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
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
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
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
        DocumentVerificationService $documentVerificationService,
        DocumentStorage $documentStorage,
        AdminNotificationMailer $adminNotificationMailer
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        // Soumission du formulaire manuel
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('documents_add', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'La session a expiré. Veuillez réessayer.');
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
            $finalType = strtolower(trim((string) $finalType)) === 'rib' ? 'rib' : $finalType;
            $ribIban = null;

            if ($finalType === 'rib') {
                $ribIban = strtoupper(preg_replace('/\s+/', '', (string) $request->request->get('rib_iban')));

                if (!$this->isValidIban($ribIban)) {
                    $this->addFlash('error', 'Merci de saisir un IBAN valide pour votre RIB.');
                    return $this->redirectToRoute('app_documents');
                }
            }

            // Vérification du type MIME
            if (!$documentVerificationService->isAllowedDocumentFile($file)) {
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

            $originalFilename = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            try {
                $filename = $documentStorage->store($file, $user->getId());
            } catch (\Throwable $e) {
                $this->logDocumentUploadError('storage', $e);
                $this->addFlash('error', 'Erreur lors du stockage du fichier. Merci de réessayer avec un nom de fichier simple, sans caractères spéciaux.');
                return $this->redirectToRoute('app_documents');
            }

            try {
                $document = new Document();
                $document->setTypeDocument($finalType);
                $document->setFilenameDocument($filename);
                $document->setOriginalFilename($originalFilename);
                $document->setMimeType($mimeType);
                $document->setFileSize($fileSize);
                $document->setDateDocument(new \DateTime());
                $document->setUser($user);
                $document->setStatus(Document::STATUS_PENDING);
                $document->setRibIban($ribIban);

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
            } catch (\Throwable $e) {
                $this->logDocumentUploadError('database', $e);
                $this->addFlash('error', 'Le fichier a été reçu, mais l’enregistrement du document a échoué. Merci de réessayer avec un nom de fichier plus court.');
            }

            return $this->redirectToRoute('app_documents');
        }

        $documents = $em->getRepository(Document::class)->findBy(['user' => $user]);

        return $this->render('user/mes_documents.html.twig', [
            'documents' => $documents,
        ]);
    }

    private function logDocumentUploadError(string $step, \Throwable $exception): void
    {
        $line = sprintf(
            "[%s] %s: %s: %s\n%s\n\n",
            (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            $step,
            get_class($exception),
            $exception->getMessage(),
            $exception->getTraceAsString()
        );

        @file_put_contents($this->getParameter('kernel.logs_dir') . '/document_upload_error.log', $line, FILE_APPEND);
    }

    private function isValidIban(string $iban): bool
    {
        if (!preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';

        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $checksum = 0;
        foreach (str_split($numeric) as $digit) {
            $checksum = ($checksum * 10 + (int) $digit) % 97;
        }

        return $checksum === 1;
    }

    /**
     * @Route("/user/documents/{id}/fichier", name="app_document_file", requirements={"id"="\d+"}, methods={"GET"})
     */
    public function documentFile(Document $document, DocumentStorage $documentStorage): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        if (!$user instanceof User || !$document->getUser() || $document->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas ouvrir ce document.');
        }

        $path = $documentStorage->resolvePath($document);
        if (!$path) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $response = new BinaryFileResponse($path);
        if ($document->getMimeType()) {
            $response->headers->set('Content-Type', $document->getMimeType());
        }
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $document->getOriginalFilename() ?: basename($path)
        );

        return $response;
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

        $paiementsReservations = [];
        $paiementsGains = [];
        $totalReserve = 0.0;
        $totalRembourse = 0.0;
        $totalGainsVerses = 0.0;

        foreach ($paiements as $paiement) {
            $reservation = $paiement->getReservation();
            if (!$reservation || !$reservation->getTrajet()) {
                continue;
            }

            $montant = (float) $paiement->getMontant();
            $montantDisponible = $paiement->getMontantDisponible();
            if ($reservation->getPassager() === $user) {
                $paiementsReservations[] = $paiement;
                if ($paiement->getStatut() === 'rembourse') {
                    $totalRembourse += $montant;
                } elseif ($paiement->getStatut() === 'rembourse_partiel') {
                    $totalRembourse += $paiement->getMontantRembourseEffectif();
                    $totalReserve += $montantDisponible;
                } elseif ($paiement->getStatut() === 'capture') {
                    $totalReserve += $montantDisponible;
                }
            }

            if ($reservation->getTrajet()->getConducteur() === $user) {
                if ($reservation->getCommissions()->count() > 0) {
                    $paiementsGains[] = $paiement;
                    $commission = $reservation->getCommissions()->first();
                    $gainConducteur = $commission ? (float) $commission->getMontantConducteur() : 0.0;
                    $totalGainsVerses += $gainConducteur;
                }
            }
        }

        return $this->render('user/mes_paiements.html.twig', [
            'paiements' => $paiements,
            'paiementsReservations' => $paiementsReservations,
            'paiementsGains' => $paiementsGains,
            'totalReserve' => $totalReserve,
            'totalRembourse' => $totalRembourse,
            'totalGainsVerses' => $totalGainsVerses,
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
     * @Route("/user/trajet/{id}/affiche", name="app_user_trajet_affiche", methods={"GET"})
     */
    public function downloadTrajetAffiche(int $id, TrajetRepository $trajetRepository, AfficheService $afficheService): BinaryFileResponse
    {
        $trajet = $trajetRepository->find($id);
        if (!$trajet) {
            throw $this->createNotFoundException('Trajet introuvable.');
        }

        if ($trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à télécharger cette affiche.");
        }

        $relativePath = $afficheService->generate($trajet);
        $absolutePath = $this->getParameter('kernel.project_dir') . '/public' . $relativePath;
        $fileName = sprintf('affiche-halogari-trajet-%d.jpg', $trajet->getId());

        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
        $response->deleteFileAfterSend(true);

        return $response;
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
        EntityManagerInterface $em,
        CancellationCommunicationService $cancellationCommunicationService
    ): Response {
        $reservation = $repo->find($id);

        // 🛑 Vérifie que c’est bien le passager qui annule
        if (!$reservation || $reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // 🔐 Vérifie le token CSRF
        if (!$this->isCsrfTokenValid('annuler_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
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

        $reservation->markCanceled(Reservation::CANCELED_BY_PASSAGER, 'Annulation demandée par le passager.');

        $paiementReservation = $reservation->getPaiement();
        $placesRestored = false;

        try {
            if ($paiementReservation && $paiementReservation->getStatut() === 'capture') {
                $paiement->rembourserSelonPolitique($reservation, false);
                $placesRestored = true;
            } elseif ($paiementReservation && $paiementReservation->getStatut() === 'autorise') {
                $paiement->annulerPaiement($reservation);
                $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());
                $placesRestored = true;
            }
        } catch (\Throwable $exception) {
            $this->addFlash('warning', "Votre réservation est annulée. Le remboursement n'a pas pu être finalisé automatiquement : HaloGari va le vérifier.");
        }

        if (!$placesRestored) {
            $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());
        }

        $cancellationCommunicationService->notifyPassengerCancellation($reservation);

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
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
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

