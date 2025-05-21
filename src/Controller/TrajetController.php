<?php

namespace App\Controller;

use App\Entity\Trajet;
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
    public function chercherResultats(string $depart, string $arrivee, string $date, string $heure, int $places, TrajetRepository $trajetRepository): Response
    {

        $trajets = $trajetRepository->findByRecherche($depart, $arrivee, $date, $places);

        return $this->render('trajet/chercherResultats.html.twig', [
            'depart' => $depart,
            'arrivee' => $arrivee,
            'dateTrajet' => $date,
            'heure' => $heure,
            'places' => $places,
            'trajets' => $trajets,
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

            if (!$user->getRib() || !$user->getJustificatifIdentite()) {
                $this->addFlash('error', 'Vous devez ajouter un RIB et une pièce d’identité avant de publier un trajet.');
                return $this->redirectToRoute('app_documents'); // ou autre page
            }

            // Si tu as des flags de validation :
            if (!$user->isRibValide() || !$user->isIdentiteValide()) {
                $this->addFlash('error', 'Vos documents doivent être validés par un administrateur avant de publier un trajet.');
                return $this->redirectToRoute('app_documents');
            }

            $trajet = new Trajet();
            $trajet->setConducteur($this->getUser());
            $trajet->setDepart($request->request->get('departure'));
            $trajet->setArrivee($request->request->get('arrival'));
            $trajet->setDateTrajet(new \DateTime($request->request->get('date')));
            $trajet->setHeureTrajet(new \DateTime($request->request->get('heure')));
            $trajet->setPlacesDisponibles((int) $request->request->get('places'));
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
                ]);

            $mailer->send($email);

            $this->addFlash('success', 'Votre trajet a bien été publié !');
            return $this->redirectToRoute('app_home'); // ou autre page de confirmation
        }

        return $this->render('trajet/publier.html.twig');
    }

    /**
     * @Route("/trajet/{id}/{ledepart}->{larrive}", name="app_trajet_show")
     */
    public function show(int $id, string $ledepart, string $larrive, TrajetRepository $trajetRepository): Response
    {
        
        // affichage d'un trajet
         $trajet = $trajetRepository->findByID($id);
        return $this->render('trajet/show.html.twig', ['trajet' => $trajet,]);
    }
}
