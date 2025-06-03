<?php

namespace App\Service;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService
{
    private WebPush $webPush;

    public function __construct(string $vapidPublicKey, string $vapidPrivateKey, string $vapidEmail)
    {
        // Configuration VAPID obligatoire pour envoyer des pushs sécurisés
        $auth = [
            'VAPID' => [
                'subject' => $vapidEmail,         // ton email pour identifier ton serveur
                'publicKey' => $vapidPublicKey,   // la clé publique générée
                'privateKey' => $vapidPrivateKey, // la clé privée générée
            ],
        ];

        // Initialise la bibliothèque WebPush avec l’authentification
        $this->webPush = new WebPush($auth);
    }

    /**
     * Envoie une notification à un abonné
     */
    public function sendNotification(array $subscriptionData, string $title, string $body): void
    {
        // Crée une instance Subscription à partir des données du navigateur
        $subscription = Subscription::create([
            'endpoint' => $subscriptionData['endpoint'],
            'publicKey' => $subscriptionData['keys']['p256dh'],
            'authToken' => $subscriptionData['keys']['auth'],
        ]);

        // Données à envoyer dans la notif
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
        ]);

        // Envoie la notif
        $this->webPush->queueNotification($subscription, $payload);

        // Exécute l’envoi
        foreach ($this->webPush->flush() as $report) {
            // Tu peux logger ici si besoin
        }
    }
}
