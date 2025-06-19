<?php

namespace App\Service;

use App\Entity\User;
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
            $endpoint = $report->getRequest()->getUri();
            if ($report->isSuccess() && $report->isSubscriptionExpired()) {
                // echo "✅ Notification envoyée à : {$endpoint}\n";
            } else {
                // echo "❌ Échec pour {$endpoint} : {$report->getReason()}\n";
            }
        }
    }

    /**
     * Envoie une notification Web Push à tous les appareils liés à un utilisateur.
     *
     * @param User   $user  L'utilisateur cible (possède plusieurs abonnements possibles)
     * @param string $title Titre de la notification
     * @param string $body  Contenu (message) de la notification
     */
    public function sendToUser(User $user, string $title, string $body): void
    {
        // Parcourt tous les abonnements push enregistrés pour cet utilisateur
        foreach ($user->getPushSubscriptions() as $sub) {
            // Envoie une notification pour chaque appareil (ou navigateur) lié
            $this->sendNotification([
                'endpoint' => $sub->getEndpoint(), // URL d'envoi spécifique à l'appareil
                'keys' => [
                    'p256dh' => $sub->getPublicKey(), // Clé publique du navigateur
                    'auth' => $sub->getAuthToken(),  // Jeton d'authentification unique
                ]
            ], $title, $body);
        }
    }

}
