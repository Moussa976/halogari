<?php

namespace App\Controller;

use App\Service\PushNotificationService;
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
    public function abonnementPush(Request $request): JsonResponse
    {
        // Récupère le corps JSON envoyé par le JS
        $content = $request->getContent();

        // Décode le JSON en tableau PHP
        $data = json_decode($content, true);

        // Option 1 : Affiche les données dans le log (pour tester)
        dump($data); // s'affiche dans le profiler Symfony

        // Option 2 : Affiche dans la console PHP (si tu as un terminal ouvert)
        file_put_contents(__DIR__ . '/../../var/push_debug.json', json_encode($data, JSON_PRETTY_PRINT));

        return new JsonResponse(['status' => 'ok']);
    }

    /**
     * @Route("/push/test", name="push_test", methods={"GET"})
     */
    public function testPush(PushNotificationService $pushNotificationService): JsonResponse
    {
        $file = __DIR__ . '/../../var/push_debug.json';

        if (!file_exists($file)) {
            return new JsonResponse(['error' => 'Aucun abonnement trouvé.'], 404);
        }

        $subscriptionData = json_decode(file_get_contents($file), true);

        $pushNotificationService->sendNotification(
            $subscriptionData,
            '🔔 Notification HaloGari',
            'Ceci est un test de notification Web Push.'
        );

        return new JsonResponse(['status' => 'Notification envoyée']);
    }
}
