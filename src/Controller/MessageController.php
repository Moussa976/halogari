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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

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
        NotificationMessageService $notificationMessageService,
        SluggerInterface $slugger
    ): JsonResponse {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                return new JsonResponse(['status' => 'error', 'message' => 'Vous devez être connecté.'], 401);
            }

            $isJson = false !== strpos((string) $request->headers->get('Content-Type'), 'application/json');
            $data = $isJson ? json_decode($request->getContent(), true) : $request->request->all();

            if (!$data) {
                return new JsonResponse(['status' => 'error', 'message' => 'Données incomplètes.'], 400);
            }

            $destinataireId = $data['destinataire'] ?? null;
            $trajetId = $data['trajet'] ?? null;
            $contenu = isset($data['contenu']) ? trim((string) $data['contenu']) : '';
            $imageFile = $request->files->get('image');

            if (!$destinataireId || !$trajetId) {
                return new JsonResponse(['status' => 'error', 'message' => 'Données incomplètes.'], 400);
            }

            if ($contenu === '' && !$imageFile instanceof UploadedFile) {
                return new JsonResponse(['status' => 'error', 'message' => 'Écrivez un message ou ajoutez une photo.'], 400);
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

            $imageFilename = null;
            if ($imageFile instanceof UploadedFile) {
                $imageFilename = $this->storeMessageImage($imageFile, $slugger);
                if (!$imageFilename) {
                    return new JsonResponse([
                        'status' => 'error',
                        'message' => 'Photo invalide. Formats acceptés : JPG, PNG ou WebP, 4 Mo maximum.',
                    ], 400);
                }
            }

            $message = new Message();
            $message->setExpediteur($user);
            $message->setDestinataire($destinataire);
            $message->setContenu($contenu);
            $message->setImageFilename($imageFilename);
            $message->setTrajet($trajet);
            $message->setCreatedAt(new \DateTime());

            $em->persist($message);
            $em->flush();

            $notificationMessageService->traiterMessageRecu($message);

            $payload = $this->messagePayload($message, $user);

            return new JsonResponse(array_merge([
                'status' => 'sent',
                'message' => $payload,
            ], $payload));
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => "Erreur interne lors de l'envoi du message.",
            ], 500);
        }
    }

    /**
     * @Route("/user/messages/{userId<\d+>}/{trajetId<\d+>}/new", name="api_conversation_new_messages", methods={"GET"})
     */
    public function newMessages(
        int $userId,
        int $trajetId,
        Request $request,
        MessageRepository $repo,
        EntityManagerInterface $em,
        CacheInterface $cache
    ): JsonResponse {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Vous devez être connecté.'], 401);
        }

        $otherUser = $em->getRepository(User::class)->find($userId);
        $trajet = $em->getRepository(Trajet::class)->find($trajetId);

        if (!$otherUser || !$trajet) {
            return new JsonResponse(['status' => 'error', 'message' => 'Conversation introuvable.'], 404);
        }

        if (!$this->findValidReservation($trajet, $currentUser, $otherUser)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Conversation non autorisée.'], 403);
        }

        $afterId = max(0, (int) $request->query->get('after', 0));

        $messages = $repo->createQueryBuilder('m')
            ->where('m.trajet = :trajet')
            ->andWhere('m.id > :afterId')
            ->andWhere('(m.expediteur = :me AND m.destinataire = :them) OR (m.expediteur = :them AND m.destinataire = :me)')
            ->setParameters([
                'me' => $currentUser,
                'them' => $otherUser,
                'trajet' => $trajet,
                'afterId' => $afterId,
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

        return new JsonResponse([
            'status' => 'ok',
            'messages' => array_map(fn (Message $message) => $this->messagePayload($message, $currentUser), $messages),
            'readMessageIds' => $this->readMessageIds($repo, $currentUser, $otherUser, $trajet),
            'otherUserTyping' => $this->isUserTyping($cache, $otherUser, $currentUser, $trajet),
        ]);
    }

    /**
     * @Route("/user/messages/{userId<\d+>}/{trajetId<\d+>}/typing", name="api_conversation_typing", methods={"POST"})
     */
    public function typing(
        int $userId,
        int $trajetId,
        Request $request,
        EntityManagerInterface $em,
        CacheInterface $cache
    ): JsonResponse {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['status' => 'error'], 401);
        }

        $otherUser = $em->getRepository(User::class)->find($userId);
        $trajet = $em->getRepository(Trajet::class)->find($trajetId);

        if (!$otherUser || !$trajet || !$this->findValidReservation($trajet, $currentUser, $otherUser)) {
            return new JsonResponse(['status' => 'error'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?: [];
        $isTyping = (bool) ($payload['typing'] ?? false);

        if ($isTyping) {
            $cache->delete($this->typingCacheKey($currentUser, $otherUser, $trajet));
            $cache->get($this->typingCacheKey($currentUser, $otherUser, $trajet), function (ItemInterface $item) {
                $item->expiresAfter(6);

                return time();
            });
        } else {
            $cache->delete($this->typingCacheKey($currentUser, $otherUser, $trajet));
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function findValidReservation(Trajet $trajet, User $userA, User $userB): ?Reservation
    {
        foreach ($trajet->getReservations() as $reservation) {
            $passager = $reservation->getPassager();
            $conducteur = $reservation->getTrajet()->getConducteur();

            if (
                (($passager === $userA && $conducteur === $userB) || ($passager === $userB && $conducteur === $userA))
                && in_array($reservation->getStatut(), ['acceptee', 'payee', 'annulee'], true)
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

    private function storeMessageImage(UploadedFile $file, SluggerInterface $slugger): ?string
    {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array((string) $file->getMimeType(), $allowedMimeTypes, true) || $file->getSize() > 4 * 1024 * 1024) {
            return null;
        }

        $directory = $this->getParameter('messages_directory');
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($originalName ?: 'message')->lower();
        $extension = $file->guessExtension() ?: 'jpg';
        $filename = $safeName . '-' . bin2hex(random_bytes(6)) . '.' . $extension;

        $file->move($directory, $filename);

        return $filename;
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

    private function messagePayload(Message $message, User $currentUser): array
    {
        $expediteur = $message->getExpediteur();
        $senderName = $expediteur ? ($expediteur->getPrenom() ?: 'profil') : 'profil';

        return [
            'id' => $message->getId(),
            'contenu' => $message->getContenu(),
            'imageUrl' => $message->getImageFilename() ? '/uploads/messages/' . $message->getImageFilename() : null,
            'createdAt' => $this->formatConversationDate($message->getCreatedAt()),
            'avatarUrl' => $expediteur && $expediteur->getPhoto() ? '/uploads/photos/' . $expediteur->getPhoto() : '/images/profil.png',
            'avatarAlt' => 'Photo de ' . $senderName,
            'profileUrl' => $expediteur ? $this->generateUrl('app_profile', ['id' => $expediteur->getId()]) : '#',
            'isVerifiedProfile' => $expediteur ? $expediteur->isProfilVerifieComplet() : false,
            'isMine' => $expediteur && (int) $expediteur->getId() === (int) $currentUser->getId(),
            'isRead' => $message->isRead(),
        ];
    }

    private function readMessageIds(MessageRepository $repo, User $currentUser, User $otherUser, Trajet $trajet): array
    {
        $ids = $repo->createQueryBuilder('m')
            ->select('m.id')
            ->where('m.trajet = :trajet')
            ->andWhere('m.expediteur = :me')
            ->andWhere('m.destinataire = :them')
            ->andWhere('m.isRead = true')
            ->setParameters([
                'me' => $currentUser,
                'them' => $otherUser,
                'trajet' => $trajet,
            ])
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($ids, 'id'));
    }

    private function typingCacheKey(User $typingUser, User $receiver, Trajet $trajet): string
    {
        return sprintf('conversation_typing_%d_%d_%d', $trajet->getId(), $typingUser->getId(), $receiver->getId());
    }

    private function isUserTyping(CacheInterface $cache, User $typingUser, User $receiver, Trajet $trajet): bool
    {
        return null !== $cache->get($this->typingCacheKey($typingUser, $receiver, $trajet), function (ItemInterface $item) {
            $item->expiresAfter(1);

            return null;
        });
    }
}
