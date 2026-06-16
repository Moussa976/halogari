<?php

namespace App\Controller;

use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\Trajet;
use App\Repository\TrajetRepository;
use App\Service\NotificationService;
use App\Service\PaiementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\ReservationRepository;


class ReservationController extends AbstractController
{
    /**
     * @Route("/mes-reservation", name="app_mesreservation", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('reservation/index.html.twig', [
            'controller_name' => 'ReservationController',
        ]);
    }

    /**
     * @Route("/reservation/{id}", name="app_reservation_direct", methods={"GET"})
     */
    public function showDirect(int $id, ReservationRepository $reservationRepository): Response
    {
        if (!$this->getUser()) {
            $this->addFlash('error', 'Vous devez être connecté pour consulter une réservation.');
            return $this->redirectToRoute('app_login');
        }

        $reservation = $reservationRepository->find($id);
        if (!$reservation) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        if ($reservation->getPassager() === $this->getUser()) {
            return $this->redirectToRoute('app_user_reservation', ['id' => $reservation->getId()]);
        }

        if ($reservation->getTrajet()->getConducteur() === $this->getUser()) {
            return $this->redirectToRoute('app_user_trajet', ['id' => $reservation->getTrajet()->getId()]);
        }

        throw $this->createAccessDeniedException("Vous n'avez pas accès à cette réservation.");
    }

    /**
     * @Route("/reservation/{id}", name="app_reservation", methods={"POST"})
     */
    public function create(Request $request, int $id, EntityManagerInterface $em, NotificationService $notifier): Response
    {
        if (!$this->getUser()) {
            $this->addFlash('error', 'Vous devez être connecté pour réserver un trajet.');
            return $this->redirectToRoute('app_login');
        }

        $trajet = $em->getRepository(Trajet::class)->find($id);
        if (!$trajet) {
            throw $this->createNotFoundException('Trajet introuvable.');
        }

        if ($trajet->isAnnule()) {
            $this->addFlash('error', 'Ce trajet est annulé et ne peut plus être réservé.');
            return $this->redirectToRoute('app_chercher');
        }

        if (!$this->isCsrfTokenValid('reserver_trajet_' . $trajet->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
        }

        if ($trajet->getConducteur() === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas réserver votre propre trajet.');
            return $this->redirectToRoute('app_user_trajet', ['id' => $trajet->getId()]);
        }

        $now = new \DateTimeImmutable();
        $trajetDateTime = new \DateTimeImmutable($trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i'));
        if ($trajetDateTime < $now) {
            $this->addFlash('error', 'Ce trajet est déjà passé.');
            return $this->redirectToRoute('app_chercher');
        }

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

        $existingReservation = $em->getRepository(Reservation::class)->findOneBy([
            'trajet' => $trajet,
            'passager' => $this->getUser(),
        ]);
        if ($existingReservation && in_array($existingReservation->getStatut(), ['en_attente', 'acceptee', 'payee'], true)) {
            $this->addFlash('info', 'Vous avez déjà une réservation active pour ce trajet.');
            return $this->redirectToRoute('app_user_reservation', ['id' => $existingReservation->getId()]);
        }

        $placesRestantes = $trajet->getPlacesDisponibles();

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
     * @Route("/reservation/{id}/accepter", name="reservation_accepter", methods={"POST"})
     */
    public function accepter(
        int $id,
        Request $request,
        ReservationRepository $reservationRepository,
        TrajetRepository $trajetRepository,
        EntityManagerInterface $em,
        NotificationService $notifier
    ): Response {
        $reservation = $reservationRepository->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }
        if (!$this->isCsrfTokenValid('reservation_action_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
        }
        $trajet = $trajetRepository->find($reservation->getTrajet()->getId());

        // 🔒 Vérification que le conducteur est bien l'utilisateur connecté
        if ($trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à effectuer cette action.");
        }

        if ($reservation->getStatut() !== 'en_attente') {
            $this->addFlash('info', 'Cette réservation a déjà été traitée.');
            return $this->redirectToRoute('app_user_trajet', ['id' => $trajet->getId()]);
        }

        // ✅ Mise à jour du statut de la réservation
        $reservation->setStatut('acceptee');

        // 💳 Création du paiement associé
        if (!$reservation->getPaiement()) {
            $paiement = new Paiement();
            $paiement->setMontant($reservation->getPrixTotal());
            $paiement->setStatut('en_attente');
            $paiement->setReservation($reservation);
            $em->persist($paiement);
        }

        // 💾 Enregistrement en base
        $em->flush();

        // 📩 Notification au passager
        $this->addFlash('success', 'Réservation acceptée. Le passager a été notifié et doit payer rapidement pour confirmer sa place.');
        $notifier->envoyerConfirmationReservation($reservation, 'acceptee');

        return $this->redirectToRoute('app_user_trajet', ['id' => $trajet->getId()]);
    }


    /**
     * @Route("/reservation/{id}/refuser", name="reservation_refuser", methods={"POST"})
     */
    public function refuser(int $id, Request $request, ReservationRepository $reservationRepository, TrajetRepository $trajetRepository, EntityManagerInterface $em, NotificationService $notifier): Response
    {
        $reservation = $reservationRepository->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }
        if (!$this->isCsrfTokenValid('reservation_action_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
        }
        $trajet = $trajetRepository->find($reservation->getTrajet()->getId());

        if ($trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à effectuer cette action.");
        }

        if ($reservation->getStatut() !== 'en_attente') {
            $this->addFlash('info', 'Cette réservation a déjà été traitée.');
            return $this->redirectToRoute('app_user_trajet', ['id' => $trajet->getId()]);
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
     * @Route("/reservation/{id}/annuler", name="reservation_annuler_legacy", methods={"POST"})
     */
    public function annuler(
        int $id,
        Request $request,
        ReservationRepository $reservationRepository,
        PaiementService $paiementService,
        EntityManagerInterface $em
    ): Response
    {
        $reservation = $reservationRepository->find($id);

        if (!$reservation || $reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à annuler cette réservation.");
        }

        // ✅ Vérifie le token CSRF
        if (!$this->isCsrfTokenValid('annuler_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException("La session a expiré. Veuillez réessayer.");
        }

        // 💡 On rend les places dans tous les cas sauf si déjà annulée ou refusée
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

        $paiement = $reservation->getPaiement();
        if ($paiement && $paiement->getStatut() === 'capture') {
            $paiementService->rembourserSelonPolitique($reservation, false);
        } elseif ($paiement && $paiement->getStatut() === 'autorise') {
            $paiementService->annulerPaiement($reservation);
            $reservation->getTrajet()->setPlacesDisponibles(
                $reservation->getTrajet()->getPlacesDisponibles() + $reservation->getPlaces()
            );
        } else {
            $reservation->getTrajet()->setPlacesDisponibles(
                $reservation->getTrajet()->getPlacesDisponibles() + $reservation->getPlaces()
            );
        }

        $reservation->markCanceled(Reservation::CANCELED_BY_PASSAGER, 'Annulation demandée par le passager.');
        $em->flush();

        $this->addFlash('info', 'Votre réservation a été annulée.');
        return $this->redirectToRoute('app_mes_reservations');
    }


}

