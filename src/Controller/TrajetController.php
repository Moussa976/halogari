<?php

namespace App\Controller;

use App\Entity\Notes;
use App\Entity\Reservation;
use App\Form\NoteConducteurType;
use App\Message\TrajetPublieMessage;
use App\Repository\UserRepository;
use App\Service\StripeConnectService;
use App\Service\TrajetAnnulationService;
use Carbon\Carbon;
use App\Entity\Trajet;
use App\Repository\NotesRepository;
use App\Repository\TrajetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

use Stripe\Stripe;
use Stripe\Account;
use Stripe\Token;



class TrajetController extends AbstractController
{
    /**
     * @Route("/chercher", name="app_chercher", methods={"GET"})
     */
    public function chercher(Request $request): Response
    {
        $depart = $request->query->get('select_departure');
        $arrivee = $request->query->get('select_arrival');
        $date = $request->query->get('date_trajet');
        $heure = $request->query->get('heure_trajet') ?? 'any';
        $places = $request->query->get('places_min') ?? 1;

        if ($request->query->count() > 0 && (!$depart || !$arrivee || !$date)) {
            $this->addFlash('error', 'Complétez le village de départ, le village d’arrivée et la date pour lancer la recherche.');
        }

        $dateRecherche = $this->normalizeSearchDate($date);

        if ($depart && $arrivee && $date && !$dateRecherche) {
            $this->addFlash('error', 'La date doit être au format jj/mm/aaaa, par exemple 05/06/2026.');
        }

        if ($depart && $arrivee && $dateRecherche) {
            return $this->redirectToRoute('app_chercherResultats', [
                'depart' => $depart,
                'arrivee' => $arrivee,
                'date' => $dateRecherche,
                'heure' => 'any',
                'places' => $places,
            ]);
        }

        return $this->render('trajet/chercher.html.twig');
    }

    /**
     * @Route("/chercher/{depart}/{arrivee}/{date}/{heure}/{places}", name="app_chercherResultats", methods={"GET"})
     */
    public function chercherResultats(string $depart, string $arrivee, string $date, string $heure, string $places, TrajetRepository $trajetRepository): Response
    {
        Carbon::setLocale('fr');
        if (!\DateTimeImmutable::createFromFormat('Y-m-d', $date)) {
            $this->addFlash('error', 'La date de recherche est invalide. Choisissez une date avec le calendrier.');
            return $this->redirectToRoute('app_chercher');
        }

        if (!ctype_digit($places) || (int) $places < 1) {
            $this->addFlash('error', 'Indiquez au moins 1 passager pour rechercher un trajet.');
            return $this->redirectToRoute('app_chercher');
        }

        $trajets = $trajetRepository->findByRecherche($depart, $arrivee, $date, $places);
        $dateFr = Carbon::createFromFormat('Y-m-d', $date);
        $dateTrajet = $dateFr->translatedFormat('l d F Y');
        $dateObj = new \DateTimeImmutable($date);

        $autresTrajets = $trajetRepository->createQueryBuilder('t')
            ->where('t.dateTrajet = :date')
            ->andWhere('t.arrivee = :arrivee')
            ->andWhere('t.depart != :depart')
            ->andWhere('t.annule != true') // Exclure les trajets annulés
            ->setParameters([
                'date' => $dateObj,
                'arrivee' => $arrivee,
                'depart' => $depart
            ])
            ->getQuery()
            ->getResult();


        $citiesFile = __DIR__ . '/../../public/cities.json';
        $villes = [];
        if (is_file($citiesFile) && is_readable($citiesFile)) {
            $decoded = json_decode((string) file_get_contents($citiesFile), true);
            $villes = is_array($decoded) ? $decoded : [];
        }

        return $this->render('trajet/chercherResultats.html.twig', [
            'depart' => $depart,
            'arrivee' => $arrivee,
            'dateTrajetFr' => $dateTrajet,
            'dateTrajet' => $date,
            'dateTrajetInput' => $dateObj->format('d/m/Y'),
            'heure' => $heure,
            'places' => $places,
            'trajets' => $trajets,
            'autresTrajets' => $autresTrajets,
            'villages' => $villes,
        ]);
    }

    /**
     * @Route("/publier", name="app_publier", methods={"GET", "POST"})
     */
    public function publier(Request $request, SessionInterface $session, EntityManagerInterface $em, StripeConnectService $stripeConnect, MessageBusInterface $bus): Response
    {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $session->set('_security.main.target_path', $request->getUri());
            $this->addFlash('error', 'Vous devez être connecté pour publier un trajet.');
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        $rib = $this->findDocumentByTypes($user, ['rib']);
        $identite = $this->findDocumentByTypes($user, ['identite', 'piece_identite', 'piece-identite']);
        $documentsReady = $rib && $identite && $rib->getStatus() === 'approved' && $identite->getStatus() === 'approved';

        if ($request->isMethod('POST')) {

            if (!$rib || !$identite) {
                if (!$rib)
                    $this->addFlash('error', 'Vous devez ajouter un RIB.');
                if (!$identite)
                    $this->addFlash('error', 'Vous devez ajouter une pièce d’identité.');
                return $this->redirectToRoute('app_documents');
            }

            if (!$documentsReady) {
                $this->addFlash('error', 'Vos documents doivent être validés par un administrateur.');
                return $this->redirectToRoute('app_documents');
            }

            $stripeConnect->creerCompteSiBesoin($user);

            $dateInput = $this->normalizeSearchDate((string) $request->request->get('date'));
            $heureInput = (string) $request->request->get('heure');
            $departure = trim((string) $request->request->get('departure'));
            $arrival = trim((string) $request->request->get('arrival'));
            $places = (int) $request->request->get('places');
            $price = (float) $request->request->get('price');
            $description = trim((string) $request->request->get('description'));
            $dateTrajet = $dateInput ? \DateTime::createFromFormat('Y-m-d', $dateInput) : false;
            $heureTrajet = \DateTime::createFromFormat('H:i', $heureInput);

            if ($departure === '' || $arrival === '') {
                $this->addFlash('error', 'Choisissez un village de départ et un village d’arrivée.');
                return $this->redirectToRoute('app_publier');
            }

            if ($departure === $arrival) {
                $this->addFlash('error', 'Le village de départ et le village d’arrivée doivent être différents.');
                return $this->redirectToRoute('app_publier');
            }

            if (!$dateTrajet || !$heureTrajet) {
                $this->addFlash('error', 'Choisissez une date au format jj/mm/aaaa et une heure valides pour le trajet.');
                return $this->redirectToRoute('app_publier');
            }

            if ($places < 1 || $places > 8) {
                $this->addFlash('error', 'Indiquez un nombre de places entre 1 et 8.');
                return $this->redirectToRoute('app_publier');
            }

            if ($price < 1) {
                $this->addFlash('error', 'Indiquez un prix par passager d’au moins 1 €.');
                return $this->redirectToRoute('app_publier');
            }

            if (mb_strlen($description) < 30) {
                $this->addFlash('error', 'Ajoutez une description d’au moins 30 caractères : lieu de rendez-vous, ponctualité, bagages ou arrêt possible.');
                return $this->redirectToRoute('app_publier');
            }

            $trajet = new Trajet();
            $trajet->setConducteur($user);
            $trajet->setDepart($departure);
            $trajet->setArrivee($arrival);
            $trajet->setDateTrajet($dateTrajet);
            $trajet->setHeureTrajet($heureTrajet);
            $trajet->setPlacesDisponibles($places);
            $trajet->setPlaces($places);
            $trajet->setPrix($price);
            $trajet->setDescription($description);

            $em->persist($trajet);
            $em->flush();

            $bus->dispatch(new TrajetPublieMessage($trajet->getId()));
            $this->addFlash('success', 'Votre trajet a bien été publié !');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('trajet/publier.html.twig', [
            'documentsReady' => (bool) $documentsReady,
        ]);
    }



    /**
     * @Route("/trajet/{id}/{ledepart}/{larrive}/{nbPlaceReservee}", name="app_trajet_show", methods={"GET"})
     */
    public function show(int $id, string $ledepart, string $larrive, string $nbPlaceReservee, TrajetRepository $trajetRepository, NotesRepository $notesRepository): Response
    {

        Carbon::setLocale('fr');

        // affichage d'un trajet
        $trajet = $trajetRepository->findByID($id);
        if (!$trajet) {
            throw $this->createNotFoundException('Trajet introuvable.');
        }

        if ($trajet->isAnnule()) {
            $this->addFlash('error', 'Ce trajet a été annulé et n\'est plus accessible.');
            return $this->redirectToRoute('app_chercher');
        }

        $now = new \DateTimeImmutable();
        $trajetDateTime = new \DateTimeImmutable($trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i'));

        if ($trajetDateTime < $now) {
            $this->addFlash('warning', 'Ce trajet est déjà passé.');
            return $this->redirectToRoute('app_chercher');
        }


        $moyenne = $notesRepository->getMoyennePourUtilisateur($trajet->getConducteur());
        $nombreAvis = $notesRepository->countAvisPourUtilisateur($trajet->getConducteur());

        $date = Carbon::parse($trajet->getDateTrajet());
        $dateTrajet = $date->translatedFormat('l d F Y');

        return $this->render('trajet/show.html.twig', [
            'trajet' => $trajet,
            'nbPlaceReservee' => $nbPlaceReservee,
            'moyenne' => $moyenne,
            'nombreAvis' => $nombreAvis,
            'dateTrajet' => $dateTrajet,
        ]);
    }

    /**
     * @Route("/user/trajet/{trajetId}/noter-passager/{passagerId}", name="app_noter_passager", methods={"GET", "POST"})
     */
    public function noterPassager(
        int $trajetId,
        int $passagerId,
        TrajetRepository $trajetRepo,
        UserRepository $userRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $conducteur = $this->getUser();
        $trajet = $trajetRepo->find($trajetId);
        $passager = $userRepo->find($passagerId);

        if (!$trajet || !$passager) {
            throw $this->createNotFoundException();
        }

        if ($trajet->getConducteur() !== $conducteur) {
            throw $this->createAccessDeniedException("Accès non autorisé.");
        }

        // Vérifier que ce passager a réservé ce trajet
        $reservation = $em->getRepository(Reservation::class)->findOneBy([
            'trajet' => $trajet,
            'passager' => $passager
        ]);

        if (!$reservation) {
            throw $this->createAccessDeniedException('Ce passager n’a pas réservé ce trajet.');
        }

        // Vérifier s’il a déjà été noté
        $existingNote = $em->getRepository(Notes::class)->findOneBy([
            'noteur' => $conducteur,
            'notePour' => $passager,
            'trajet' => $trajet
        ]);

        if ($existingNote) {
            $this->addFlash('info', 'Vous avez déjà noté ce passager.');
            return $this->redirectToRoute('app_user_trajet', ['id' => $trajetId]);
        }

        $note = new Notes();
        $form = $this->createForm(NoteConducteurType::class, $note);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $note->setNoteur($conducteur);
            $note->setNotePour($passager);
            $note->setTrajet($trajet);

            $em->persist($note);
            $em->flush();

            $this->addFlash('success', 'Note enregistrée pour ' . $passager->getPrenom() . '.');
            return $this->redirectToRoute('app_user_trajet', ['id' => $trajetId]);
        }

        return $this->render('notes/noter_passager.html.twig', [
            'form' => $form->createView(),
            'trajet' => $trajet,
            'passager' => $passager
        ]);
    }

    /**
     * @Route("/user/trajet/{id}/noter-conducteur", name="app_noter_conducteur", methods={"GET", "POST"})
     */
    public function noterConducteur(
        int $id,
        TrajetRepository $trajetRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $trajet = $trajetRepo->find($id);
        $user = $this->getUser();

        // Vérification que l'utilisateur est un passager de ce trajet
        $reservation = $em->getRepository(Reservation::class)->findOneBy([
            'trajet' => $trajet,
            'passager' => $user
        ]);

        if (!$reservation) {
            throw $this->createAccessDeniedException('Vous n’avez pas réservé ce trajet.');
        }

        // Empêcher la double notation
        $existingNote = $em->getRepository(Notes::class)->findOneBy([
            'noteur' => $user,
            'notePour' => $trajet->getConducteur(),
            'trajet' => $trajet
        ]);

        if ($existingNote) {
            $this->addFlash('info', 'Vous avez déjà noté ce conducteur pour ce trajet.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        $note = new Notes();
        $form = $this->createForm(NoteConducteurType::class, $note);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $note->setNoteur($user);
            $note->setNotePour($trajet->getConducteur());
            $note->setTrajet($trajet);

            $em->persist($note);
            $em->flush();

            $this->addFlash('success', 'Merci pour votre avis !');
            return $this->redirectToRoute('app_mes_reservations');
        }

        return $this->render('notes/noter_conducteur.html.twig', [
            'form' => $form->createView(),
            'trajet' => $trajet
        ]);
    }

    /**
     * Permet au conducteur d’annuler son trajet.
     * @Route("/user/trajet/{id}/annuler", name="trajet_annuler", methods={"POST"})
     */
    public function annulerTrajet(
        int $id,
        Request $request,
        TrajetRepository $trajetRepository,
        TrajetAnnulationService $annulationService
    ): Response {
        $trajet = $trajetRepository->find($id);

        if (!$trajet || $trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Accès non autorisé.");
        }

        if (!$this->isCsrfTokenValid('annuler_trajet_' . $trajet->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $annulationService->annulerTrajet($trajet);

        $this->addFlash('success', 'Le trajet a été annulé et les passagers ont été informés.');
        return $this->redirectToRoute('app_mes_trajets');
    }
    private function findDocumentByTypes($user, array $allowedTypes)
    {
        if (!$user || !method_exists($user, 'getDocuments')) {
            return null;
        }

        $allowed = array_map(static fn(string $type): string => strtolower(trim($type)), $allowedTypes);
        foreach ($user->getDocuments() as $document) {
            $docType = strtolower(trim((string) $document->getTypeDocument()));
            if (in_array($docType, $allowed, true)) {
                return $document;
            }
        }

        return null;
    }

    private function normalizeSearchDate(?string $date): ?string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $date);
            if ($parsed && $parsed->format($format) === $date) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }
}
