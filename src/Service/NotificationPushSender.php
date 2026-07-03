<?php

namespace App\Service;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationPushSender
{
    private PushNotificationService $pushNotificationService;
    private NotificationRepository $notificationRepository;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(PushNotificationService $pushNotificationService, NotificationRepository $notificationRepository, UrlGeneratorInterface $urlGenerator)
    {
        $this->pushNotificationService = $pushNotificationService;
        $this->notificationRepository = $notificationRepository;
        $this->urlGenerator = $urlGenerator;
    }

    public function send(Notification $notification): void
    {
        $user = $notification->getUser();
        if (!$user) {
            return;
        }

        $this->pushNotificationService->sendToUser(
            $user,
            $notification->getTitre() ?: 'HaloGari',
            $notification->getContenu() ?: 'Vous avez une nouvelle notification.',
            $this->resolveClickUrl($notification),
            $this->countUnreadNotifications($notification)
        );
    }

    private function resolveClickUrl(Notification $notification): string
    {
        if ($notification->getId()) {
            return $this->urlGenerator->generate('notification_voir', ['id' => $notification->getId()]);
        }

        return $notification->getLien() ?: '/user/mes-notifications';
    }

    private function countUnreadNotifications(Notification $notification): int
    {
        $user = $notification->getUser();
        if (!$user) {
            return 0;
        }

        $count = (int) $this->notificationRepository->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.lu = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        if (!$notification->isLu() && !$notification->getId()) {
            ++$count;
        }

        return $count;
    }
}
