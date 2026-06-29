<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class CancellationCommunicationService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function notifyPassengerCancellation(Reservation $reservation): void
    {
        $trajet = $reservation->getTrajet();
        $passager = $reservation->getPassager();
        $conducteur = $trajet ? $trajet->getConducteur() : null;

        if (!$trajet || !$passager || !$conducteur) {
            return;
        }

        $content = sprintf(
            "Bonjour, j'ai annulé ma réservation pour le trajet %s → %s du %s à %s. Les places ont été libérées.",
            $trajet->getDepart(),
            $trajet->getArrivee(),
            $trajet->getDateTrajet()->format('d/m/Y'),
            $trajet->getHeureTrajet()->format('H:i')
        );

        $this->createMessage($passager, $conducteur, $reservation, $content);
        $this->createNotification(
            $conducteur,
            'Réservation annulée',
            sprintf('%s a annulé sa réservation pour %s → %s.', $passager->getPrenom(), $trajet->getDepart(), $trajet->getArrivee()),
            '/user/messages/' . $passager->getId() . '/' . $trajet->getId()
        );
    }

    public function notifyDriverTripCancellation(Reservation $reservation): void
    {
        $trajet = $reservation->getTrajet();
        $passager = $reservation->getPassager();
        $conducteur = $trajet ? $trajet->getConducteur() : null;

        if (!$trajet || !$passager || !$conducteur) {
            return;
        }

        $content = sprintf(
            "Bonjour, j'ai annulé le trajet %s → %s du %s à %s. Votre réservation est annulée. Si un paiement était enregistré, HaloGari applique la règle de remboursement prévue.",
            $trajet->getDepart(),
            $trajet->getArrivee(),
            $trajet->getDateTrajet()->format('d/m/Y'),
            $trajet->getHeureTrajet()->format('H:i')
        );

        $this->createMessage($conducteur, $passager, $reservation, $content);
        $this->createNotification(
            $passager,
            'Trajet annulé',
            sprintf('Le conducteur a annulé le trajet %s → %s.', $trajet->getDepart(), $trajet->getArrivee()),
            '/user/messages/' . $conducteur->getId() . '/' . $trajet->getId()
        );
    }

    private function createMessage(User $from, User $to, Reservation $reservation, string $content): void
    {
        $message = (new Message())
            ->setExpediteur($from)
            ->setDestinataire($to)
            ->setTrajet($reservation->getTrajet())
            ->setContenu($content);

        $this->em->persist($message);
    }

    private function createNotification(User $user, string $title, string $content, string $link): void
    {
        $notification = (new Notification())
            ->setUser($user)
            ->setType('annulation')
            ->setTitre($title)
            ->setContenu($content)
            ->setLien($link);

        $this->em->persist($notification);
    }
}
