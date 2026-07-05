<?php

namespace App\EventSubscriber;

use App\Service\UserDataRetentionCleaner;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class UserDataRetentionSubscriber implements EventSubscriberInterface
{
    private UserDataRetentionCleaner $cleaner;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(UserDataRetentionCleaner $cleaner, CacheInterface $cache, LoggerInterface $logger)
    {
        $this->cleaner = $cleaner;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['cleanupOncePerDay', -255],
        ];
    }

    public function cleanupOncePerDay(TerminateEvent $event): void
    {
        if (method_exists($event, 'isMainRequest') && !$event->isMainRequest()) {
            return;
        }

        $key = 'halogari_user_data_cleanup_' . (new \DateTimeImmutable('now', new \DateTimeZone('Indian/Mayotte')))->format('Ymd');

        $alreadyDone = $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter(90000);

            return false;
        });

        if ($alreadyDone) {
            return;
        }

        try {
            $result = $this->cleaner->cleanup(30, false);
            $this->logger->info('Nettoyage automatique des notifications et messages utilisateurs.', [
                'notifications' => $result['notifications'],
                'messages' => $result['messages'],
                'images' => $result['images'],
                'cutoff' => $result['cutoff']->format(\DateTimeInterface::ATOM),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Echec du nettoyage automatique des notifications et messages utilisateurs.', [
                'exception' => $exception,
            ]);

            return;
        }

        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter(90000);

            return true;
        });
    }
}
