<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Trajet;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Service\NotificationMessageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use App\Utils\DateHelper;

class MessageController extends AbstractController
{
    /**
     * @Route("/user/messages", name="app_message")
     */
    public function index(MessageRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

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
                continue; // sécurité si message sans trajet
            }

            $other = $message->getExpediteur() === $user ? $message->getDestinataire() : $message->getExpediteur();
            $key = $other->getId() . '_' . $trajet->getId();

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'user' => $other,
                    'trajet' => $trajet,
                    'lastMessage' => $message,
                    'unreadCount' => 0,
                ];
            }

            // Incrémente si message non lu par l'utilisateur
            if ($message->getDestinataire() === $user && !$message->isRead()) {
                $grouped[$key]['unreadCount']++;
            }
        }

        return $this->render('message/index.html.twig', [
            'conversations' => $grouped,
        ]);
    }

    /**
     * @Route("/user/messages/unread", name="api_messages_unread")
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
     * @Route("/user/messages/{userId<\d+>}/{trajetId<\d+>}", name="app_conversation")
     */
    public function conversation(
        int $userId,
        int $trajetId,
        MessageRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $currentUser = $this->getUser();
        $otherUser = $em->getRepository(User::class)->find($userId);
        $trajet = $em->getRepository(Trajet::class)->find($trajetId);

        if (!$otherUser || !$trajet) {
            throw $this->createNotFoundException("Utilisateur ou trajet introuvable.");
        }

        $reservation = null;
        foreach ($trajet->getReservations() as $r) {
            if (
                ($r->getPassager() === $currentUser && $r->getTrajet()->getConducteur() === $otherUser) ||
                ($r->getPassager() === $otherUser && $r->getTrajet()->getConducteur() === $currentUser)
            ) {
                $reservation = $r;
                break;
            }
        }

        if (!$reservation || !in_array($reservation->getStatut(), ['acceptee', 'payee'])) {
            $this->addFlash('danger', 'La messagerie est désactivée tant que la réservation n\'est pas acceptée.');
            return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId()]);
        }


        // Récupère tous les messages pour CE trajet entre ces 2 utilisateurs
        $messages = $repo->createQueryBuilder('m')
            ->where('m.trajet = :trajet')
            ->andWhere('
            (m.expediteur = :me AND m.destinataire = :them)
            OR
            (m.expediteur = :them AND m.destinataire = :me)
        ')
            ->setParameters([
                'me' => $currentUser,
                'them' => $otherUser,
                'trajet' => $trajet
            ])
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        // Marquer les messages reçus comme lus
        foreach ($messages as $message) {
            if ($message->getDestinataire() === $currentUser && !$message->isRead()) {
                $message->setIsRead(true);
            }
        }
        $em->flush();

        $ladateTrajet = DateHelper::formatDateFr($trajet->getDateTrajet(), 'l d F Y');

        return $this->render('message/conversation.html.twig', [
            'messages' => $messages,
            'otherUser' => $otherUser,
            'trajet' => $trajet,
            'ladateTrajet' => $ladateTrajet
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

            if (!$data) {
                return new JsonResponse(['status' => 'error', 'message' => 'Mauvais JSON'], 400);
            }

            $destinataireId = $data['destinataire'] ?? null;
            $trajetId = $data['trajet'] ?? null;
            $contenu = $data['contenu'] ?? null;

            if (!$user || !$destinataireId || !$contenu || !$trajetId) {
                return new JsonResponse(['status' => 'error', 'message' => 'Données incomplètes.'], 400);
            }

            $destinataire = $em->getRepository(User::class)->find($destinataireId);
            $trajet = $em->getRepository(Trajet::class)->find($trajetId);

            if (!$destinataire || !$trajet) {
                return new JsonResponse(['status' => 'error', 'message' => 'Utilisateur ou trajet introuvable.'], 404);
            }

            // 🔒 Vérification de réservation
            $reservation = null;
            foreach ($trajet->getReservations() as $r) {
                if (
                    ($r->getPassager() === $user && $r->getTrajet()->getConducteur() === $destinataire) ||
                    ($r->getPassager() === $destinataire && $r->getTrajet()->getConducteur() === $user)
                ) {
                    $reservation = $r;
                    break;
                }
            }

            if (!$reservation || !in_array($reservation->getStatut(), ['acceptee', 'payee'])) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Vous ne pouvez pas envoyer de message tant que la réservation n’a pas été acceptée.'
                ], 403);
            }

            // ✅ Création du message
            $message = new Message();
            $message->setExpediteur($user);
            $message->setDestinataire($destinataire);
            $message->setContenu($contenu);
            $message->setTrajet($trajet);
            $message->setCreatedAt(new \DateTime());

            $em->persist($message);
            $em->flush();

            // ✅ Notification et e-mail
            $notificationMessageService->traiterMessageRecu($message);

            $avatarHtml = $this->renderView('partials/avatar_response.html.twig', [
                'user' => $user
            ]);

            return new JsonResponse([
                'status' => 'sent',
                'contenu' => $message->getContenu(),
                'createdAt' => $message->getCreatedAt()->format('d/m/Y H:i'),
                'avatarHtml' => $avatarHtml

            ]);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }



}
