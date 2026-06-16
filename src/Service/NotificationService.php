<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class NotificationService
{
    private MailerInterface $mailer;
    private Environment $twig;
    private EntityManagerInterface $em;
    private AdminNotificationMailer $adminNotificationMailer;

    public function __construct(
        MailerInterface $mailer,
        Environment $twig,
        EntityManagerInterface $em,
        AdminNotificationMailer $adminNotificationMailer
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->em = $em;
        $this->adminNotificationMailer = $adminNotificationMailer;
    }

    public function envoyerConfirmationReservation(Reservation $reservation, string $etat): void
    {
        $passager = $reservation->getPassager();
        $trajet = $reservation->getTrajet();

        $subject = $etat === 'acceptee'
            ? 'Votre réservation a été acceptée !'
            : 'Votre réservation a été refusée.';

        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($passager->getEmail())
            ->subject($subject)
            ->html($this->twig->render('emails/confirmation_reservation.html.twig', [
                'reservation' => $reservation,
                'etat' => $etat,
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $this->createNotification(
            $passager,
            'reservation',
            $etat === 'acceptee' ? 'Réservation acceptée : paiement attendu' : 'Réservation refusée',
            $etat === 'acceptee'
                ? sprintf('Votre demande pour %s → %s est acceptée. Enregistrez votre paiement pour confirmer votre place.', $trajet->getDepart(), $trajet->getArrivee())
                : sprintf('Votre demande pour %s → %s a été refusée.', $trajet->getDepart(), $trajet->getArrivee()),
            '/user/reservation/' . $reservation->getId()
        );
    }

    public function demanderValidationReservation(Reservation $reservation): void
    {
        $conducteur = $reservation->getTrajet()->getConducteur();

        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($conducteur->getEmail())
            ->subject('Nouvelle demande de réservation à valider')
            ->html($this->twig->render('emails/demande_validation_reservation.html.twig', [
                'reservation' => $reservation,
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $this->createNotification(
            $conducteur,
            'reservation',
            'Nouvelle demande de réservation',
            sprintf(
                '%s demande %d place(s) pour le trajet %s → %s.',
                $reservation->getPassager()->getPrenom(),
                $reservation->getPlaces(),
                $reservation->getTrajet()->getDepart(),
                $reservation->getTrajet()->getArrivee()
            ),
            '/user/trajet/' . $reservation->getTrajet()->getId()
        );

        $this->adminNotificationMailer->notify(
            'Nouvelle réservation à suivre',
            sprintf(
                "%s %s demande %d place(s) pour %s → %s.\nConducteur : %s %s <%s>",
                $reservation->getPassager()->getPrenom(),
                $reservation->getPassager()->getNom(),
                $reservation->getPlaces(),
                $reservation->getTrajet()->getDepart(),
                $reservation->getTrajet()->getArrivee(),
                $conducteur->getPrenom(),
                $conducteur->getNom(),
                $conducteur->getEmail()
            ),
            '/admin/reservations'
        );
    }

    public function envoyerConfirmationPaiement(Reservation $reservation): void
    {
        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($reservation->getPassager()->getEmail())
            ->subject('Paiement enregistré - Réservation HaloGari')
            ->html($this->twig->render('emails/paiement_confirme.html.twig', [
                'reservation' => $reservation,
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);
    }

    public function envoyerReservationAnnuleeParConducteur(Reservation $reservation): void
    {
        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($reservation->getPassager()->getEmail())
            ->subject('Votre réservation a été annulée par le conducteur')
            ->html($this->twig->render('emails/reservation_annulee_conducteur.html.twig', [
                'reservation' => $reservation,
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);
    }

    public function envoyerRemboursementEffectue(Reservation $reservation): void
    {
        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($reservation->getPassager()->getEmail())
            ->subject('Votre remboursement a été effectué')
            ->html($this->twig->render('emails/remboursement_effectue.html.twig', [
                'reservation' => $reservation,
                'trajet' => $reservation->getTrajet(),
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);
    }

    public function envoyerEchecPaiement(Reservation $reservation): void
    {
        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($reservation->getPassager()->getEmail())
            ->subject('Paiement annulé ou expiré')
            ->html($this->twig->render('emails/echec_paiement.html.twig', [
                'reservation' => $reservation,
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $this->createNotification(
            $reservation->getPassager(),
            'paiement',
            'Paiement annulé ou expiré',
            "Votre paiement n'a pas abouti. Vérifiez votre réservation.",
            '/user/reservation/' . $reservation->getId()
        );
    }

    public function envoyerPaiementCapture(Reservation $reservation): void
    {
        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($reservation->getPassager()->getEmail())
            ->subject('Votre paiement est confirmé')
            ->html($this->twig->render('emails/paiement_capture.html.twig', [
                'reservation' => $reservation,
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $this->createNotification(
            $reservation->getPassager(),
            'paiement',
            'Paiement confirmé',
            'Votre paiement est confirmé pour cette réservation.',
            '/user/reservation/' . $reservation->getId()
        );

        $this->adminNotificationMailer->notify(
            'Paiement confirmé à contrôler',
            sprintf(
                "Paiement confirmé pour la réservation #%d.\nPassager : %s %s <%s>\nTrajet : %s → %s",
                $reservation->getId(),
                $reservation->getPassager()->getPrenom(),
                $reservation->getPassager()->getNom(),
                $reservation->getPassager()->getEmail(),
                $reservation->getTrajet()->getDepart(),
                $reservation->getTrajet()->getArrivee()
            ),
            '/admin/paiements'
        );
    }

    private function createNotification($user, string $type, string $title, string $content, ?string $link = null): void
    {
        $notif = new Notification();
        $notif->setUser($user);
        $notif->setType($type);
        $notif->setTitre($title);
        $notif->setContenu($content);
        $notif->setLien($link);
        $this->em->persist($notif);
        $this->em->flush();
    }
}
