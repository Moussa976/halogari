<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Reservation;
use App\Entity\Trajet;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Service\NotificationMessageService;
use App\Utils\DateHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MessageController extends AbstractController
{
    /**
     * @Route("/user/messages", name="app_message", methods={"GET"})
     */
    public function index(MessageRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('message/index.html.twig', [
            'conversations' => $this->buildConversationList($repo, $user),
        ]);
    }

    /**
     * @Route("/user/messages/unread", name="api_messages_unread", methods={"GET"})
     */
    public function unreadMessages(MessageRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['unreadCount' => 0]);
        }

        $count = $repo->createQueryBuilder('m')
            ->select('count(m.id)')
            ->where('m.destinataire = :user')
            ->andWhere('m.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse(['unreadCount' => $count]);
    }

    /**
     * @Route("/user/messages/{userId<\d+>}/{trajetId<\d+>}", name="app_conversation", methods={"GET"})
     */
    public function conversation(
        int $userId,
        int $trajetId,
        MessageRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $otherUser = $em->getRepository(User::class)->find($userId);
        $trajet = $em->getRepository(Trajet::class)->find($trajetId);

        if (!$otherUser || !$trajet) {
            throw $this->createNotFoundException('Utilisateur ou trajet introuvable.');
        }

        if (!$this->findValidReservation($trajet, $currentUser, $otherUser)) {
            $this->addFlash('danger', "Vous ne pouvez pas discuter tant que la réservation n'a pas été acceptée.");
            return $this->redirectToRoute('app_mes_reservations');
        }

        $messages = $repo->createQueryBuilder('m')
            ->where('m.trajet = :trajet')
            ->andWhere('(m.expediteur = :me AND m.destinataire = :them) OR (m.expediteur = :them AND m.destinataire = :me)')
            ->setParameters([
                'me' => $currentUser,
                'them' => $otherUser,
                'trajet' => $trajet,
            ])
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($messages as $message) {
            if ($message->getDestinataire() === $currentUser && !$message->isRead()) {
                $message->setIsRead(true);
            }
        }

        $em->flush();

        return $this->render('message/conversation.html.twig', [
            'messages' => $messages,
            'otherUser' => $otherUser,
            'trajet' => $trajet,
            'ladateTrajet' => DateHelper::formatDateFr($trajet->getDateTrajet(), 'l d F Y'),
            'conversations' => $this->buildConversationList($repo, $currentUser),
            'activeConversationKey' => $otherUser->getId() . '_' . $trajet->getId(),
        ]);
    }

    /**
     * @Route("/user/messages/send", name="api_message_send", methods={"POST"})
     */
    public function sendMessage(
        Request $request,
        EntityManagerInterface $em,
        NotificationMessageService $notificationMessageService
    ): JsonResponse {
        try {
            $user = $this->getUser();
            $data = json_decode($request->getContent(), true);

            if (!$user instanceof User || !$data) {
                return new JsonResponse(['status' => 'error', 'message' => 'Données incomplètes.'], 400);
            }

            $destinataireId = $data['destinataire'] ?? null;
            $trajetId = $data['trajet'] ?? null;
            $contenu = isset($data['contenu']) ? trim((string) $data['contenu']) : '';

            if (!$destinataireId || !$trajetId || $contenu === '') {
                return new JsonResponse(['status' => 'error', 'message' => 'Données incomplètes.'], 400);
            }

            if (mb_strlen($contenu) > 2000) {
                return new JsonResponse(['status' => 'error', 'message' => 'Message trop long (2000 caractères max).'], 400);
            }

            $destinataire = $em->getRepository(User::class)->find($destinataireId);
            $trajet = $em->getRepository(Trajet::class)->find($trajetId);

            if (!$destinataire || !$trajet) {
                return new JsonResponse(['status' => 'error', 'message' => 'Utilisateur ou trajet introuvable.'], 404);
            }

            if ((int) $destinataire->getId() === (int) $user->getId()) {
                return new JsonResponse(['status' => 'error', 'message' => 'Envoi vers soi-même impossible.'], 400);
            }

            if (!$this->findValidReservation($trajet, $user, $destinataire)) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => "Vous ne pouvez pas envoyer de message tant que la réservation n'a pas été acceptée.",
                ], 403);
            }

            $message = new Message();
            $message->setExpediteur($user);
            $message->setDestinataire($destinataire);
            $message->setContenu($contenu);
            $message->setTrajet($trajet);
            $message->setCreatedAt(new \DateTime());

            $em->persist($message);
            $em->flush();

            $notificationMessageService->traiterMessageRecu($message);

            return new JsonResponse([
                'status' => 'sent',
                'contenu' => $message->getContenu(),
                'createdAt' => $this->formatConversationDate($message->getCreatedAt()),
                'avatarUrl' => $user->getPhoto() ? '/uploads/photos/' . $user->getPhoto() : '/images/profil.png',
                'avatarAlt' => 'Photo de ' . ($user->getPrenom() ?: 'profil'),
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => "Erreur interne lors de l'envoi du message.",
            ], 500);
        }
    }

    private function findValidReservation(Trajet $trajet, User $userA, User $userB): ?Reservation
    {
        foreach ($trajet->getReservations() as $reservation) {
            $passager = $reservation->getPassager();
            $conducteur = $reservation->getTrajet()->getConducteur();

            if (
                (($passager === $userA && $conducteur === $userB) || ($passager === $userB && $conducteur === $userA))
                && in_array($reservation->getStatut(), ['acceptee', 'payee'], true)
            ) {
                return $reservation;
            }
        }

        return null;
    }

    private function buildConversationList(MessageRepository $repo, User $user): array
    {
        $messages = $repo->createQueryBuilder('m')
            ->where('m.expediteur = :user OR m.destinataire = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $grouped = [];

        foreach ($messages as $message) {
            $trajet = $message->getTrajet();
            if (!$trajet) {
                continue;
            }

            $other = $message->getExpediteur() === $user ? $message->getDestinataire() : $message->getExpediteur();
            if (!$other) {
                continue;
            }

            $key = $other->getId() . '_' . $trajet->getId();
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'user' => $other,
                    'trajet' => $trajet,
                    'lastMessage' => $message,
                    'unreadCount' => 0,
                ];
            }

            if ($message->getDestinataire() === $user && !$message->isRead()) {
                $grouped[$key]['unreadCount']++;
            }
        }

        return $grouped;
    }

    private function formatConversationDate(\DateTimeInterface $date): string
    {
        $now = new \DateTimeImmutable();
        $today = $now->format('Y-m-d');
        $yesterday = $now->modify('-1 day')->format('Y-m-d');
        $messageDate = $date->format('Y-m-d');
        $time = $date->format('H:i');

        if ($messageDate === $today) {
            return "Aujourd'hui à " . $time;
        }

        if ($messageDate === $yesterday) {
            return 'Hier à ' . $time;
        }

        return $date->format('d/m/Y à H:i');
    }
}
