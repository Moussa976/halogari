<?php

namespace App\EventSubscriber;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

class NotificationUrlSubscriber implements EventSubscriberInterface
{
    private $security;
    private $notificationRepo;
    private $em;

    public function __construct(Security $security, NotificationRepository $notificationRepo, EntityManagerInterface $em)
    {
        $this->security = $security;
        $this->notificationRepo = $notificationRepo;
        $this->em = $em;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest())
            return;

        $user = $this->security->getUser();
        if (!$user)
            return;

        $currentUri = $event->getRequest()->getRequestUri();

        $notifs = $this->notificationRepo->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.lu = false')
            ->andWhere('n.lien = :lien')
            ->setParameter('user', $user)
            ->setParameter('lien', $currentUri)
            ->getQuery()
            ->getResult();

        foreach ($notifs as $notif) {
            $notif->setLu(true);
        }

        if (!empty($notifs)) {
            $this->em->flush();
        }

    }
}
