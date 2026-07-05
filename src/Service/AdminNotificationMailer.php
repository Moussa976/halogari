<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AdminNotificationMailer
{
    private MailerInterface $mailer;
    private UserRepository $userRepository;
    private EntityManagerInterface $em;
    private NotificationPushSender $notificationPushSender;

    public function __construct(
        MailerInterface $mailer,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        NotificationPushSender $notificationPushSender
    )
    {
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->em = $em;
        $this->notificationPushSender = $notificationPushSender;
    }

    public function notify(string $subject, string $message, ?string $adminUrl = null): void
    {
        $body = trim($message);
        if ($adminUrl) {
            $body .= "\n\nAcces admin : " . $adminUrl;
        }

        $email = (new Email())
            ->from(MailAddressProvider::adminSender())
            ->to(MailAddressProvider::ADMIN_EMAIL)
            ->subject('[HaloGari Admin] ' . $subject)
            ->text($body);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            // Une alerte email ne doit jamais bloquer une action utilisateur ou admin.
        }

        $this->notifySuperAdmins($subject, $message, $adminUrl);
    }

    private function notifySuperAdmins(string $subject, string $message, ?string $adminUrl): void
    {
        try {
            $superAdmins = $this->userRepository->createQueryBuilder('u')
                ->where('u.roles LIKE :role')
                ->setParameter('role', '%"ROLE_SUPER_ADMIN"%')
                ->getQuery()
                ->getResult();

            if (!$superAdmins) {
                return;
            }

            $notifications = [];
            foreach ($superAdmins as $superAdmin) {
                if (!$superAdmin instanceof User) {
                    continue;
                }

                $notification = (new Notification())
                    ->setUser($superAdmin)
                    ->setType('admin')
                    ->setTitre($subject)
                    ->setContenu($message)
                    ->setLien($adminUrl);

                $this->em->persist($notification);
                $notifications[] = $notification;
            }

            $this->em->flush();

            foreach ($notifications as $notification) {
                $this->notificationPushSender->send($notification);
            }
        } catch (\Throwable $exception) {
            // Une notification admin interne ne doit jamais bloquer l'action principale.
        }
    }
}
