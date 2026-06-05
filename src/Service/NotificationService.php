<?php

namespace App\Service;

use App\Entity\Notification;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service chargé d’envoyer tous les e-mails liés aux réservations.
 * Gère les notifications pour passager et conducteur.
 */
class NotificationService
{
    private MailerInterface $mailer;
    private Environment $twig;
    private EntityManagerInterface $em;

    public function __construct(MailerInterface $mailer, Environment $twig, EntityManagerInterface $em)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->em = $em;
    }

    /**
     * Envoie un e-mail au passager pour l’informer que sa réservation a été acceptée ou refusée.
     *
     * @param Reservation $reservation La réservation concernée
     * @param string $etat "acceptee" ou "refusee"
     */
    public function envoyerConfirmationReservation(Reservation $reservation, string $etat): void
    {
        $passager = $reservation->getPassager();
        $trajet = $reservation->getTrajet();

        $subject = $etat === 'acceptee'
            ? 'Votre réservation a été acceptée !'
            : 'Votre réservation a été refusée.';

        $html = $this->twig->render('emails/confirmation_reservation.html.twig', [
            'reservation' => $reservation,
            'etat' => $etat,
        ]);

        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($passager->getEmail())
            ->subject($subject)
            ->html($html)
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $this->createNotification(
            $passager,
            'reservation',
            $etat === 'acceptee' ? 'Reservation acceptee' : 'Reservation refusee',
            sprintf(
                'Votre demande pour %s -> %s a ete %s.',
                $trajet->getDepart(),
                $trajet->getArrivee(),
                $etat === 'acceptee' ? 'acceptee' : 'refusee'
            ),
            '/user/reservation/' . $reservation->getId()
        );
    }

    /**
     * Envoie un e-mail au conducteur pour l’informer d’une nouvelle demande de réservation.
     *
     * @param Reservation $reservation La réservation à valider
     */
    public function demanderValidationReservation(Reservation $reservation): void
    {
        $conducteur = $reservation->getTrajet()->getConducteur();

        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($conducteur->getEmail())
            ->subject('Nouvelle demande de réservation à valider')
            ->html($this->twig->render('emails/demande_validation_reservation.html.twig', [
                'reservation' => $reservation
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $this->createNotification(
            $conducteur,
            'reservation',
            'Nouvelle demande de reservation',
            sprintf(
                '%s demande %d place(s) pour le trajet %s -> %s.',
                $reservation->getPassager()->getPrenom(),
                $reservation->getPlaces(),
                $reservation->getTrajet()->getDepart(),
                $reservation->getTrajet()->getArrivee()
            ),
            '/user/trajet/' . $reservation->getTrajet()->getId()
        );
    }

    /**
     * Envoie un e-mail au passager pour confirmer que son paiement a été autorisé.
     * Le débit réel se fera plus tard (capture manuelle par l’admin).
     *
     * @param Reservation $reservation La réservation dont le paiement a été autorisé
     */
    public function envoyerConfirmationPaiement(Reservation $reservation): void
    {
        $passager = $reservation->getPassager();

        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($passager->getEmail())
            ->subject('Paiement autorisé - Réservation HaloGari')
            ->html($this->twig->render('emails/paiement_confirme.html.twig', [
                'reservation' => $reservation
            ]))
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);
    }

    /**
     * Envoie un e-mail au passager quand sa réservation est annulée par le conducteur.
     *
     * @param Reservation $reservation La réservation concernée
     */
    public function envoyerReservationAnnuleeParConducteur(Reservation $reservation): void
    {
        // 🎯 On récupère le passager concerné par la réservation
        $passager = $reservation->getPassager();

        // 📧 On construit l'e-mail avec expéditeur, destinataire, sujet et contenu HTML
        $email = (new Email())
            ->from('moussa@halogari.yt') // adresse expéditrice (non-répondre)
            ->to($passager->getEmail()) // adresse du passager
            ->subject('Votre réservation a été annulée par le conducteur') // objet de l'e-mail
            ->html(
                // Le contenu HTML de l'e-mail est généré depuis un template Twig
                $this->twig->render('emails/reservation_annulee_conducteur.html.twig', [
                    'reservation' => $reservation // on passe la réservation au template
                ])
            )
            // 📎 On ajoute le logo de HaloGari en tant qu'image embarquée
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        // ✉️ On envoie l'e-mail via le mailer Symfony
        $this->mailer->send($email);
    }

    /**
     * Envoie un email au passager pour confirmer qu’un remboursement a bien été effectué.
     *
     * @param Reservation $reservation La réservation concernée par le remboursement.
     */
    public function envoyerRemboursementEffectue(Reservation $reservation): void
    {
        // 👤 On récupère le passager et le trajet associé
        $user = $reservation->getPassager();
        $trajet = $reservation->getTrajet();

        // 📧 Création de l’e-mail de confirmation du remboursement
        $email = (new Email())
            ->from('moussa@halogari.yt') // Expéditeur
            ->to($user->getEmail()) // Destinataire (le passager)
            ->subject('Votre remboursement a été effectué 💸') // Sujet du mail
            ->html(
                // Contenu HTML rendu avec Twig
                $this->twig->render('emails/remboursement_effectue.html.twig', [
                    'reservation' => $reservation,
                    'trajet' => $trajet,
                ])
            )
            // 🖼️ On embarque le logo pour affichage dans le mail
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        // 📤 Envoi de l’e-mail
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
            'Paiement annule ou expire',
            'Votre paiement n\'a pas abouti. Verifiez votre reservation.',
            '/user/reservation/' . $reservation->getId()
        );
    }

    public function envoyerPaiementCapture(Reservation $reservation): void
{
    $email = (new Email())
        ->from('moussa@halogari.yt')
        ->to($reservation->getPassager()->getEmail())
        ->subject('Votre paiement a été capturé ✅')
        ->html($this->twig->render('emails/paiement_capture.html.twig', [
            'reservation' => $reservation,
        ]))
        ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

    $this->mailer->send($email);
    $this->createNotification(
        $reservation->getPassager(),
        'paiement',
        'Paiement capture',
        'Votre paiement est confirme pour cette reservation.',
        '/user/reservation/' . $reservation->getId()
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
