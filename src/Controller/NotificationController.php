<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;


class NotificationController extends AbstractController
{
    /**
     * @Route("/user/mes-notifications", name="app_notifications")
     */
    public function index(NotificationRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $notifications = $repo->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * @Route("/user/notification/voir/{id}", name="notification_voir")
     */
    public function voir(Notification $notification, EntityManagerInterface $em): Response
    {
        // Marquer comme lue
        if (!$notification->isLu()) {
            $notification->setLu(true);
            $em->flush();
        }

        // dd($notification->getLien());

        // Redirection vers le lien réel
        return $this->redirect($notification->getLien() ?? $this->generateUrl('app_notifications'));
    }



    /**
     * @Route("/user/notifications/unread", name="api_notifications_unread", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function unreadCount(NotificationRepository $repo): JsonResponse
    {
        $user = $this->getUser();

        $count = $repo->createQueryBuilder('n')
            ->select('count(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.lu = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse(['unreadCount' => (int) $count]);
    }

    /**
     * @Route("/user/notifications/list", name="api_notifications_list", methods={"GET"})
     */
    public function listPartial(NotificationRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $notifications = $repo->findBy(['user' => $user, 'lu' => false], ['createdAt' => 'DESC']);

        return $this->render('partials/_notifications_list.html.twig', [
            'notifications' => $notifications
        ]);
    }


}
