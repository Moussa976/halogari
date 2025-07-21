<?php

namespace App\Service;

use App\Entity\Trajet;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\NotificationService;
use App\Service\PaiementService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class TrajetAnnulationService
{
    private EntityManagerInterface $em;
    private NotificationService $notifier;
    private ReservationRepository $reservationRepository;
    private PaiementService $paiementService;
    private MailerInterface $mailer;

    public function __construct(
        EntityManagerInterface $em,
        NotificationService $notifier,
        ReservationRepository $reservationRepository,
        PaiementService $paiementService,
        MailerInterface $mailer
    ) {
        $this->em = $em;
        $this->notifier = $notifier;
        $this->reservationRepository = $reservationRepository;
        $this->paiementService = $paiementService;
        $this->mailer = $mailer;
    }

    /**
     * Annule un trajet, rembourse les passagers et notifie toutes les parties.
     */
    public function annulerTrajet(Trajet $trajet): void
    {
        // 🚫 Marquer comme annulé
        $trajet->setAnnule(true);

        // 🔁 Pour chaque réservation acceptée/payée
        foreach ($trajet->getReservations() as $reservation) {
            if (in_array($reservation->getStatut(), ['acceptee', 'payee'])) {
                $reservation->setStatut('annulee');

                // 💳 Paiement : annuler ou rembourser
                $this->paiementService->annulerPaiement($reservation);

                // 🔔 Notification interne
                $this->notifier->envoyerReservationAnnuleeParConducteur($reservation);

                // 📧 Email au passager
                $email = (new TemplatedEmail())
                    ->to($reservation->getPassager()->getEmail())
                    ->subject('Trajet annulé - remboursement en cours')
                    ->htmlTemplate('emails/trajet_annule_passager.html.twig')
                    ->context([
                        'trajet' => $trajet,
                        'reservation' => $reservation,
                    ])
                    ->embedFromPath(
                        __DIR__ . '/../../public/images/logo.png',
                        'logo_halogari'
                    ); // 👈 logo attaché en inline
                $this->mailer->send($email);
            }
        }

        // 📧 Email au conducteur
        $emailConducteur = (new TemplatedEmail())
            ->to($trajet->getConducteur()->getEmail())
            ->subject('Vous avez annulé un trajet')
            ->htmlTemplate('emails/trajet_annule_conducteur.html.twig')
            ->context([
                'trajet' => $trajet,
            ])
            ->embedFromPath(
                __DIR__ . '/../../public/images/logo.png',
                'logo_halogari'
            ); // 👈 logo attaché en inline
        $this->mailer->send($emailConducteur);

        $this->em->flush();
    }
}
