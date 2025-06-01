<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use App\Entity\Reservation;

class NotificationService
{
    private MailerInterface $mailer;
    private Environment $twig;

    public function __construct(MailerInterface $mailer, Environment $twig)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

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
            ->from('no-reply@halogari.yt')
            ->to($passager->getEmail())
            ->subject($subject)
            ->html($html);

        // Ajout du logo en pièce jointe intégrée (cid)
        $email->embedFromPath( __DIR__ . '/../../public/images/logo.png', 'logo_halogari' );

        $this->mailer->send($email);
    }

    public function demanderValidationReservation(Reservation $reservation): void
    {
        $email = (new Email())
            ->from('no-reply@halogari.yt')
            ->to($reservation->getTrajet()->getConducteur()->getEmail())
            ->subject('Nouvelle demande de réservation à valider')
            ->html($this->twig->render('emails/demande_validation_reservation.html.twig', [
                'reservation' => $reservation
            ]))
            ->embedFromPath( __DIR__ . '/../../public/images/logo.png', 'logo_halogari' );

        $this->mailer->send($email);
    }
}
