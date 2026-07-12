<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Notes;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class NotificationService
{
    private MailerInterface $mailer;
    private Environment $twig;
    private EntityManagerInterface $em;
    private AdminNotificationMailer $adminNotificationMailer;
    private UrlGeneratorInterface $urlGenerator;
    private NotificationPushSender $notificationPushSender;

    public function __construct(
        MailerInterface $mailer,
        Environment $twig,
        EntityManagerInterface $em,
        AdminNotificationMailer $adminNotificationMailer,
        UrlGeneratorInterface $urlGenerator,
        NotificationPushSender $notificationPushSender
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->em = $em;
        $this->adminNotificationMailer = $adminNotificationMailer;
        $this->urlGenerator = $urlGenerator;
        $this->notificationPushSender = $notificationPushSender;
    }

    public function envoyerConfirmationReservation(Reservation $reservation, string $etat): void
    {
        $passager = $reservation->getPassager();
        $trajet = $reservation->getTrajet();

        $subject = $etat === 'acceptee'
            ? 'Votre réservation a été acceptée !'
            : 'Votre réservation a été refusée.';

        $email = (new Email())
            ->from(MailAddressProvider::publicSender())
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
            ->from(MailAddressProvider::publicSender())
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
            ->from(MailAddressProvider::publicSender())
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
            ->from(MailAddressProvider::publicSender())
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
            ->from(MailAddressProvider::publicSender())
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
            ->from(MailAddressProvider::publicSender())
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
            ->from(MailAddressProvider::publicSender())
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

    public function envoyerCodeMonteeValide(Reservation $reservation): void
    {
        $trajet = $reservation->getTrajet();

        $this->createNotification(
            $reservation->getPassager(),
            'reservation',
            'Code de montée validé',
            sprintf('Votre montée est validée pour le trajet %s → %s.', $trajet->getDepart(), $trajet->getArrivee()),
            '/user/reservation/' . $reservation->getId()
        );

        $this->adminNotificationMailer->notify(
            'Code de montée validé',
            sprintf(
                "Code de montée validé pour la réservation #%d.\nPassager : %s %s <%s>\nTrajet : %s → %s",
                $reservation->getId(),
                $reservation->getPassager()->getPrenom(),
                $reservation->getPassager()->getNom(),
                $reservation->getPassager()->getEmail(),
                $trajet->getDepart(),
                $trajet->getArrivee()
            ),
            '/admin/reservations'
        );
    }

    public function envoyerNouvelAvis(Notes $note): void
    {
        $destinataire = $note->getNotePour();
        $auteur = $note->getNoteur();
        $trajet = $note->getTrajet();

        if (!$destinataire || !$auteur || !$trajet || !$destinataire->getEmail()) {
            return;
        }

        $url = $this->urlGenerator->generate('app_profilePublic', [
            'id' => $auteur->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from(MailAddressProvider::publicSender())
            ->to($destinataire->getEmail())
            ->subject('Vous avez reçu un avis sur HaloGari')
            ->html($this->twig->render('emails/nouvel_avis.html.twig', [
                'note' => $note,
                'url' => $url,
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $this->createNotification(
            $destinataire,
            'avis',
            'Nouvel avis reçu',
            sprintf('%s vous a laissé une note après le trajet %s → %s.', $auteur->getPrenom(), $trajet->getDepart(), $trajet->getArrivee()),
            $this->urlGenerator->generate('app_profilePublic', ['id' => $destinataire->getId()])
        );
    }

    public function demanderAvisPassager(Reservation $reservation): void
    {
        $passager = $reservation->getPassager();
        $trajet = $reservation->getTrajet();

        if (!$passager || !$trajet || !$passager->getEmail()) {
            return;
        }

        $url = $this->urlGenerator->generate('app_noter_conducteur', [
            'id' => $trajet->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from(MailAddressProvider::publicSender())
            ->to($passager->getEmail())
            ->subject('Comment s’est passé votre trajet ?')
            ->html($this->twig->render('emails/demande_avis_passager.html.twig', [
                'reservation' => $reservation,
                'url' => $url,
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $this->createNotification(
            $passager,
            'avis',
            'Donnez votre avis',
            sprintf('Votre trajet %s → %s est terminé. Vous pouvez noter le conducteur.', $trajet->getDepart(), $trajet->getArrivee()),
            $this->urlGenerator->generate('app_noter_conducteur', ['id' => $trajet->getId()])
        );
    }

    public function demanderAvisConducteur(Reservation $reservation): void
    {
        $passager = $reservation->getPassager();
        $trajet = $reservation->getTrajet();
        $conducteur = $trajet ? $trajet->getConducteur() : null;

        if (!$passager || !$trajet || !$conducteur || !$conducteur->getEmail()) {
            return;
        }

        $url = $this->urlGenerator->generate('app_noter_passager', [
            'trajetId' => $trajet->getId(),
            'passagerId' => $passager->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from(MailAddressProvider::publicSender())
            ->to($conducteur->getEmail())
            ->subject('Pensez à noter votre passager')
            ->html($this->twig->render('emails/demande_avis_conducteur.html.twig', [
                'reservation' => $reservation,
                'url' => $url,
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $this->createNotification(
            $conducteur,
            'avis',
            'Notez votre passager',
            sprintf('Le trajet %s → %s est terminé. Vous pouvez noter %s.', $trajet->getDepart(), $trajet->getArrivee(), $passager->getPrenom()),
            $this->urlGenerator->generate('app_noter_passager', [
                'trajetId' => $trajet->getId(),
                'passagerId' => $passager->getId(),
            ])
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
        $this->notificationPushSender->send($notif);
    }
}
