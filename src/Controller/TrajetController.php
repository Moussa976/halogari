<?php

namespace App\Controller;

use App\Entity\Notes;
use App\Entity\Reservation;
use App\Form\NoteConducteurType;
use App\Repository\UserRepository;
use Carbon\Carbon;
use App\Entity\Trajet;
use App\Repository\NotesRepository;
use App\Repository\TrajetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

class TrajetController extends AbstractController
{
    /**
     * @Route("/chercher", name="app_chercher")
     */
    public function chercher(Request $request): Response
    {
        $depart = $request->query->get('select_departure');
        $arrivee = $request->query->get('select_arrival');
        $date = $request->query->get('date_trajet');
        $heure = $request->query->get('heure_trajet') ?? 'any';
        $places = $request->query->get('places_min') ?? 1;



        // Vérification simple
        if ($depart && $arrivee && $date && \DateTime::createFromFormat('Y-m-d', $date)) {
            return $this->redirectToRoute('app_chercherResultats', [
                'depart' => $depart,
                'arrivee' => $arrivee,
                'date' => $date,
                'heure' => 'any', // ou tu peux le retirer aussi de la route
                'places' => $places,
            ]);
        }

        return $this->render('trajet/chercher.html.twig');
    }


    /**
     * @Route("/chercher/{depart}/{arrivee}/{date}/{heure}/{places}", name="app_chercherResultats")
     */
    public function chercherResultats(string $depart, string $arrivee, string $date, string $heure, string $places, TrajetRepository $trajetRepository): Response
    {


        Carbon::setLocale('fr');

        $trajets = $trajetRepository->findByRecherche($depart, $arrivee, $date, $places);

        $dateFr = Carbon::parse($date);
        $dateTrajet = $dateFr->translatedFormat('l d F Y');

        // Chargement depuis fichier JSON
        $villes = json_decode(file_get_contents(__DIR__ . '/../../public/cities.json'), true);

        return $this->render('trajet/chercherResultats.html.twig', [
            'depart' => $depart,
            'arrivee' => $arrivee,
            'dateTrajetFr' => $dateTrajet,
            'dateTrajet' => $date,
            'heure' => $heure,
            'places' => $places,
            'trajets' => $trajets,
            'villages' => $villes,
        ]);
    }

    /**
     * @Route("/publier", name="app_publier", methods={"GET", "POST"})
     */
    public function publier(Request $request, SessionInterface $session, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $session->set('_security.main.target_path', $request->getUri());
            $this->addFlash('error', 'Vous devez être connecté pour publier un trajet.');
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {

            $user = $this->getUser();

            $rib = $user->getDocumentByType('RIB');
            $identite = $user->getDocumentByType("identite");

            if (!$rib || !$identite) {
                $this->addFlash('error', 'Vous devez ajouter un RIB et une pièce d’identité.');
                return $this->redirectToRoute('app_documents');
            }

            if ($rib->getStatus() !== 'approved' || $identite->getStatus() !== 'approved') {
                $this->addFlash('error', 'Vos documents doivent être validés par un administrateur.');
                return $this->redirectToRoute('app_documents');
            }


            $trajet = new Trajet();
            $trajet->setConducteur($this->getUser());
            $trajet->setDepart($request->request->get('departure'));
            $trajet->setArrivee($request->request->get('arrival'));
            $trajet->setDateTrajet(new \DateTime($request->request->get('date')));
            $trajet->setHeureTrajet(new \DateTime($request->request->get('heure')));
            $trajet->setPlacesDisponibles((int) $request->request->get('places'));
            $trajet->setPlaces((int) $request->request->get('places'));
            $trajet->setPrix((float) $request->request->get('price'));

            $trajet->setDescription($request->request->get('description'));

            $em->persist($trajet);
            $em->flush();

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@halogari.yt', 'HaloGari'))
                ->to($user->getEmail())
                ->subject('Votre trajet a été publié')
                ->htmlTemplate('emails/trajet_publie.html.twig')
                ->context([
                    'user' => $user,
                    'trajet' => $trajet,
                ])
                ->embedFromPath($this->getParameter('kernel.project_dir') . '/public/images/logo.png', 'logo_halogari');

            $mailer->send($email);

            $this->addFlash('success', 'Votre trajet a bien été publié !');
            return $this->redirectToRoute('app_home'); // ou autre page de confirmation
        }

        return $this->render('trajet/publier.html.twig');
    }



    /**
     * @Route("/trajet/{id}/{ledepart}/{larrive}/{nbPlaceReservee}", name="app_trajet_show")
     */
    public function show(int $id, string $ledepart, string $larrive, string $nbPlaceReservee, TrajetRepository $trajetRepository, NotesRepository $notesRepository): Response
    {

        Carbon::setLocale('fr');

        // affichage d'un trajet
        $trajet = $trajetRepository->findByID($id);
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
     * @Route("/user/trajet/{trajetId}/noter-passager/{passagerId}", name="app_noter_passager")
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
        throw $this->createAccessDeniedException();
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
     * @Route("/user/trajet/{id}/noter-conducteur", name="app_noter_conducteur")
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



}
