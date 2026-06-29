<?php

// src/Service/NotificationMessageService.php
namespace App\Service;

use App\Entity\Message;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class NotificationMessageService
{
    private $em;
    private $mailer;
    private ParameterBagInterface $params;
    private NotificationPushSender $notificationPushSender;

    public function __construct(EntityManagerInterface $em, MailerInterface $mailer, ParameterBagInterface $params, NotificationPushSender $notificationPushSender)
    {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->params = $params;
        $this->notificationPushSender = $notificationPushSender;
    }

    public function traiterMessageRecu(Message $message): void
    {
        $destinataire = $message->getDestinataire();
        $expediteur = $message->getExpediteur();
        $trajet = $message->getTrajet();

        // 🔔 Notification interne
        $notif = new Notification();
        $notif->setUser($destinataire);
        $notif->setType('message');
        $notif->setTitre('Nouveau message de ' . $expediteur->getPrenom());
        $notif->setContenu('Vous avez reçu un nouveau message à propos d’un trajet.');
        $notif->setLien('/user/messages/' . $expediteur->getId() . '/' . $trajet->getId());
        $this->em->persist($notif);

        // 📸 Déterminer la photo de l'expéditeur
        $projectDir = (string) $this->params->get('kernel.project_dir');
        $cheminPhoto = $expediteur->getPhoto()
            ? $projectDir . '/public/uploads/photos/' . $expediteur->getPhoto()
            : $projectDir . '/public/images/profil.png';

        if (!file_exists($cheminPhoto)) {
            $cheminPhoto = $projectDir . '/public/images/profil.png';
        }

        // 📧 Email au destinataire
        $email = (new TemplatedEmail())
            ->from('moussa@halogari.yt')
            ->to($destinataire->getEmail())
            ->subject('Nouveau message de ' . $expediteur->getPrenom())
            ->htmlTemplate('emails/nouveau_message.html.twig')
            ->context([
                'message' => $message,
                'expediteur' => $expediteur,
                'destinataire' => $destinataire,
            ])
            ->embedFromPath($projectDir . '/public/images/logo.png', 'logo_halogari')
            ->embedFromPath($cheminPhoto, 'profil');

        $this->mailer->send($email);
        $this->em->flush();
        $this->notificationPushSender->send($notif);
    }

}
