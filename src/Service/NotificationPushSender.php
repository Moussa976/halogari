<?php

namespace App\Service;

use App\Entity\Notification;
use App\Repository\NotificationRepository;

class NotificationPushSender
{
    private PushNotificationService $pushNotificationService;
    private NotificationRepository $notificationRepository;

    public function __construct(PushNotificationService $pushNotificationService, NotificationRepository $notificationRepository)
    {
        $this->pushNotificationService = $pushNotificationService;
        $this->notificationRepository = $notificationRepository;
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
            $notification->getLien() ?: '/user/mes-notifications',
            $this->countUnreadNotifications($notification)
        );
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
