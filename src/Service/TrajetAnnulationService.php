<?php

namespace App\Service;

use App\Entity\Trajet;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\NotificationService;

class TrajetAnnulationService
{
    private EntityManagerInterface $em;
    private NotificationService $notifier;
    private ReservationRepository $reservationRepository;

    public function __construct(
        EntityManagerInterface $em,
        NotificationService $notifier,
        ReservationRepository $reservationRepository
    ) {
        $this->em = $em;
        $this->notifier = $notifier;
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * Annule un trajet et notifie tous les passagers concernés.
     *
     * @param Trajet $trajet Le trajet à annuler
     */
    public function annulerTrajet(Trajet $trajet): void
    {
        // 🚫 Marque le trajet comme annulé
        $trajet->setAnnule(true);

        // 🔁 Récupère toutes les réservations du trajet
        foreach ($trajet->getReservations() as $reservation) {
            if (in_array($reservation->getStatut(), ['acceptee', 'payee'])) {
                // 📝 Met à jour la réservation
                $reservation->setStatut('annulee');

                // 📧 Envoie une notification au passager concerné
                $this->notifier->envoyerReservationAnnuleeParConducteur($reservation);
            }
        }

        $this->em->flush();
    }
}
