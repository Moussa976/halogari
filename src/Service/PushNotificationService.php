<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private ?WebPush $webPush = null;
    private EntityManagerInterface $em;

    public function __construct(string $vapidPublicKey, string $vapidPrivateKey, string $vapidEmail, EntityManagerInterface $em)
    {
        $this->em = $em;

        if ($vapidPublicKey === '' || $vapidPrivateKey === '' || $vapidEmail === '') {
            return;
        }

        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => $vapidEmail,
                'publicKey' => $vapidPublicKey,
                'privateKey' => $vapidPrivateKey,
            ],
        ]);
    }

    public function sendNotification(array $subscriptionData, string $title, string $body, ?string $url = null, ?int $badgeCount = null): void
    {
        $this->dispatch($subscriptionData, $title, $body, $url, $badgeCount);
    }

    public function sendToUser(User $user, string $title, string $body, ?string $url = null, ?int $badgeCount = null): void
    {
        if (!$this->webPush) {
            return;
        }

        $removed = false;

        foreach ($user->getPushSubscriptions() as $subscription) {
            $result = $this->dispatch([
                'endpoint' => $subscription->getEndpoint(),
                'keys' => [
                    'p256dh' => $subscription->getPublicKey(),
                    'auth' => $subscription->getAuthToken(),
                ],
            ], $title, $body, $url, $badgeCount);

            if ($result['expired']) {
                $this->em->remove($subscription);
                $removed = true;
            }
        }

        if ($removed) {
            $this->em->flush();
        }
    }

    /**
     * @return array{sent: bool, expired: bool}
     */
    private function dispatch(array $subscriptionData, string $title, string $body, ?string $url = null, ?int $badgeCount = null): array
    {
        if (!$this->webPush) {
            return ['sent' => false, 'expired' => false];
        }

        $subscription = Subscription::create([
            'endpoint' => $subscriptionData['endpoint'],
            'publicKey' => $subscriptionData['keys']['p256dh'],
            'authToken' => $subscriptionData['keys']['auth'],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?: '/',
            'badgeCount' => $badgeCount,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload)) {
            return ['sent' => false, 'expired' => false];
        }

        $this->webPush->queueNotification($subscription, $payload);
        $sent = false;
        $expired = false;

        foreach ($this->webPush->flush() as $report) {
            $sent = $sent || $report->isSuccess();
            $expired = $expired || $report->isSubscriptionExpired();
        }

        return ['sent' => $sent, 'expired' => $expired];
    }
}
