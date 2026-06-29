<?php

namespace App\Service;

use App\Entity\Notification;

class NotificationPushSender
{
    private PushNotificationService $pushNotificationService;

    public function __construct(PushNotificationService $pushNotificationService)
    {
        $this->pushNotificationService = $pushNotificationService;
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
            $notification->getLien() ?: '/user/mes-notifications'
        );
    }
}
