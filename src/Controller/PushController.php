<?php

namespace App\Controller;

use App\Repository\PushSubscriptionRepository;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PushController extends AbstractController
{
    /**
     * @Route("/js/push-notif.js", name="js_push_notif", methods={"GET"})
     */
    public function pushNotifJs(): Response
    {
        return $this->render('push/push-notif.js.twig', [
            'vapidPublicKey' => $_ENV['VAPID_PUBLIC_KEY'], // ou via parameter bag
        ], new Response('', 200, ['Content-Type' => 'application/javascript']));
    }

    /**
     * @Route("/abonnement-push", name="push_abonnement", methods={"POST"})
     */
    public function abonnementPush(
        Request $request,
        EntityManagerInterface $em,
        PushSubscriptionRepository $repo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['endpoint'])) {
            return new JsonResponse(['error' => 'Requête invalide'], 400);
        }

        $endpoint = $data['endpoint'];
        $publicKey = $data['keys']['p256dh'] ?? '';
        $authToken = $data['keys']['auth'] ?? '';
        $userAgent = $request->headers->get('User-Agent');

        $subscription = $repo->findOneBy(['endpoint' => $endpoint]) ?? new \App\Entity\PushSubscription();

        $subscription->setEndpoint($endpoint);
        $subscription->setPublicKey($publicKey);
        $subscription->setAuthToken($authToken);
        $subscription->setUserAgent($userAgent);

        if ($this->getUser()) {
            $subscription->setUser($this->getUser());
        }

        $em->persist($subscription);
        $em->flush();

        return new JsonResponse(['status' => '✅ Abonnement enregistré']);
    }


    /**
     * @Route("/push/test", name="push_test", methods={"GET"})
     */
    public function testPush(
        PushNotificationService $pushNotificationService,
        PushSubscriptionRepository $repo
    ): JsonResponse {
        // Si l'utilisateur est connecté, on essaie d'utiliser son abonnement
        if ($this->getUser()) {
            $subscription = $repo->findOneBy(['user' => $this->getUser()], ['id' => 'DESC']);
        } else {
            // Sinon, on prend le dernier abonnement trouvé
            $subscription = $repo->findOneBy([], ['id' => 'DESC']);
        }

        if (!$subscription) {
            return new JsonResponse(['error' => 'Aucun abonnement trouvé en base.'], 404);
        }

        // Préparer les données pour le service
        $subscriptionData = [
            'endpoint' => $subscription->getEndpoint(),
            'keys' => [
                'p256dh' => $subscription->getPublicKey(),
                'auth' => $subscription->getAuthToken()
            ]
        ];

        // Envoi de la notification test
        $pushNotificationService->sendNotification(
            $subscriptionData,
            '🔔 Test HaloGari',
            'Ceci est une notification test depuis la base de données.'
        );

        return new JsonResponse(['status' => '✅ Notification envoyée depuis la base']);
    }


    /**
     * @Route("/push/test-user", name="push_test_user")
     */
    public function testUserPush(
        PushNotificationService $pushService
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            return new Response("❌ Aucun utilisateur connecté", 403);
        }

        $pushService->sendToUser(
            $user,
            '📬 Test personnalisé',
            'Ceci est un test de notification pour votre compte HaloGari.'
        );

        return new Response("✅ Notification envoyée à tous tes appareils !");
    }


}
