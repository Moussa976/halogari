<?php

namespace App\EventSubscriber;

use App\Repository\PlatformSettingRepository;
use App\Service\AdminNotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FacebookTokenExpirySubscriber implements EventSubscriberInterface
{
    private const TOKEN_EXPIRES_AT = 'facebook.token_expires_at';
    private const ALERT_SENT_FOR = 'facebook.token_expiry_alert_sent_for';

    private PlatformSettingRepository $settings;
    private EntityManagerInterface $em;
    private AdminNotificationMailer $adminNotificationMailer;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        PlatformSettingRepository $settings,
        EntityManagerInterface $em,
        AdminNotificationMailer $adminNotificationMailer,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->em = $em;
        $this->adminNotificationMailer = $adminNotificationMailer;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['notifyBeforeExpiry', -240],
        ];
    }

    public function notifyBeforeExpiry(TerminateEvent $event): void
    {
        if (method_exists($event, 'isMainRequest') && !$event->isMainRequest()) {
            return;
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Indian/Mayotte'));
        $cacheKey = 'halogari_facebook_token_expiry_check_' . $today->format('Ymd');

        $alreadyChecked = $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(90000);

            return false;
        });

        if ($alreadyChecked) {
            return;
        }

        try {
            $this->checkExpiry($today);
        } catch (\Throwable $exception) {
            $this->logger->error('Echec du controle d expiration du token Facebook.', [
                'exception' => $exception,
            ]);

            return;
        }

        $this->cache->delete($cacheKey);
        $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(90000);

            return true;
        });
    }

    private function checkExpiry(\DateTimeImmutable $today): void
    {
        $expiresAtValue = trim((string) $this->settings->getValue(self::TOKEN_EXPIRES_AT, ''));
        if ($expiresAtValue === '') {
            return;
        }

        $expiresAt = \DateTimeImmutable::createFromFormat('!Y-m-d', $expiresAtValue, new \DateTimeZone('Indian/Mayotte'));
        if (!$expiresAt) {
            return;
        }

        $daysLeft = (int) $today->diff($expiresAt)->format('%r%a');
        if ($daysLeft < 0 || $daysLeft > 10) {
            return;
        }

        if ((string) $this->settings->getValue(self::ALERT_SENT_FOR, '') === $expiresAtValue) {
            return;
        }

        $this->adminNotificationMailer->notify(
            'Token Facebook bientôt expiré',
            sprintf(
                "Le token Facebook expire le %s. Il reste %d jour(s). Pense à le renouveler dans les paramètres admin.",
                $expiresAt->format('d/m/Y'),
                $daysLeft
            ),
            '/admin/parametres#settings-facebook'
        );

        $this->settings->setValue(self::ALERT_SENT_FOR, $expiresAtValue);
        $this->em->flush();
    }
}
