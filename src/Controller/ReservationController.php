<?php

namespace App\Controller;

use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\Trajet;
use App\Repository\TrajetRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\ReservationRepository;


class ReservationController extends AbstractController
{
    /**
     * @Route("/mes-reservation", name="app_mesreservation")
     */
    public function index(): Response
    {
        return $this->render('reservation/index.html.twig', [
            'controller_name' => 'ReservationController',
        ]);
    }

    /**
     * @Route("/reservation/{id}", name="app_reservation", methods={"GET", "POST"})
     */
    public function create(Request $request, int $id, EntityManagerInterface $em, NotificationService $notifier): Response
    {
        if (!$this->getUser()) {
            $this->addFlash('error', 'Vous devez être connecté pour réserver un trajet.');
            return $this->redirectToRoute('app_login');
        }

        $trajet = $em->getRepository(Trajet::class)->find($id);
        $places = (int) $request->request->get('placesReservees');

        // Vérification : null ou inférieur à 1
        if ($places <= 0) {
            $this->addFlash('error', "Vous devez réserver au moins 1 place.");
            return $this->redirectToRoute('app_trajet_show', [
                'id' => $trajet->getId(),
                "ledepart" => $trajet->getDepart(),
                "larrive" => $trajet->getArrivee(),
                "nbPlaceReservee" => $places
            ]);
        }

        // Calcul du nombre de places déjà prises
        $placesDejaPrises = 0;
        foreach ($trajet->getReservations() as $res) {
            if (in_array($res->getStatut(), ['en_attente', 'acceptee', 'payee'])) {
                $placesDejaPrises += $res->getPlaces();
            }
        }


        $placesRestantes = $trajet->getPlacesDisponibles() - $placesDejaPrises;

        if ($places > $placesRestantes) {
            $this->addFlash('error', "Il ne reste que $placesRestantes place(s) disponibles pour ce trajet.");
            return $this->redirectToRoute('app_trajet_show', [
                'id' => $trajet->getId(),
                "ledepart" => $trajet->getDepart(),
                "larrive" => $trajet->getArrivee(),
                "nbPlaceReservee" => $places
            ]);
        }

        // Création de la réservation
        $reservation = new Reservation();
        $reservation->setTrajet($trajet);
        $reservation->setPassager($this->getUser());
        $reservation->setPlaces($places);
        $reservation->setPrix($trajet->getPrix());
        $reservation->setPrixTotal($trajet->getPrix() * $places);
        $reservation->setStatut('en_attente'); // important

        $em->persist($reservation);

        // 🔒 Réserver les places immédiatement
        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() - $places);

        $em->flush();

        $notifier->demanderValidationReservation($reservation);

        $this->addFlash('success', "Réservation confirmée pour $places place(s).");

        return $this->render('reservation/reservation_confirmation.html.twig', [
            'reservation' => $reservation
        ]);
    }

    /**
     * @Route("/reservation/{id}/accepter", name="reservation_accepter")
     */
    public function accepter(
        int $id,
        ReservationRepository $reservationRepository,
        TrajetRepository $trajetRepository,
        EntityManagerInterface $em,
        NotificationService $notifier
    ): Response {
        $reservation = $reservationRepository->find($id);
        $trajet = $trajetRepository->find($reservation->getTrajet()->getId());

        // 🔒 Vérification que le conducteur est bien l'utilisateur connecté
        if ($trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à effectuer cette action.");
        }

        // ✅ Mise à jour du statut de la réservation
        $reservation->setStatut('acceptee');

        // 💳 Création du paiement associé
        $paiement = new Paiement();
        $paiement->setMontant($reservation->getPrixTotal()); // total = prix * nb places
        $paiement->setStatut('en_attente');
        $paiement->setReservation($reservation);

        $em->persist($paiement); // On persiste explicitement (pas en cascade)

        // 💾 Enregistrement en base
        $em->flush();

        // 📩 Notification au passager
        $this->addFlash('success', 'Réservation acceptée avec succès.');
        $notifier->envoyerConfirmationReservation($reservation, 'acceptee');

        return $this->redirectToRoute('app_user_trajet', ['id' => $trajet->getId()]);
    }


    /**
     * @Route("/reservation/{id}/refuser", name="reservation_refuser")
     */
    public function refuser(int $id, ReservationRepository $reservationRepository, TrajetRepository $trajetRepository, EntityManagerInterface $em, NotificationService $notifier): Response
    {
        $reservation = $reservationRepository->find($id);
        $trajet = $trajetRepository->find($reservation->getTrajet()->getId());

        if ($trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à effectuer cette action.");
        }

        // ✅ Remet les places à disposition
        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getPlaces());

        $reservation->setStatut('refusee');
        $em->flush();

        $this->addFlash('success', 'Réservation refusée.');
        $notifier->envoyerConfirmationReservation($reservation, 'refusee');

        return $this->redirectToRoute('app_user_trajet', ['id' => $trajet->getId()]);
    }

    /**
     * @Route("/reservation/{id}/annuler", name="reservation_annuler", methods={"POST"})
     */
    public function annuler(int $id, Request $request, ReservationRepository $reservationRepository, EntityManagerInterface $em): Response
    {
        $reservation = $reservationRepository->find($id);

        if (!$reservation || $reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à annuler cette réservation.");
        }

        // ✅ Vérifie le token CSRF
        if (!$this->isCsrfTokenValid('annuler_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException("Token CSRF invalide.");
        }

        // 💡 On rend les places dans tous les cas sauf si déjà annulée ou refusée
        if (!in_array($reservation->getStatut(), ['refusee', 'annulee'])) {
            $reservation->getTrajet()->setPlacesDisponibles(
                $reservation->getTrajet()->getPlacesDisponibles() + $reservation->getPlaces()
            );
        }

        $reservation->setStatut('annulee');
        $em->flush();

        $this->addFlash('info', 'Votre réservation a été annulée.');
        return $this->redirectToRoute('app_mes_reservations');
    }


}
