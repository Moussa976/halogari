<?php

namespace App\EventSubscriber;

use App\Repository\PlatformSettingRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class PublicSiteAvailabilitySubscriber implements EventSubscriberInterface
{
    private const PUBLIC_ENABLED = 'production.public_enabled';
    private const OFFLINE_MESSAGE = 'production.offline_message';

    private PlatformSettingRepository $settings;
    private Environment $twig;

    public function __construct(PlatformSettingRepository $settings, Environment $twig)
    {
        $this->settings = $settings;
        $this->twig = $twig;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -64],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (method_exists($event, 'isMainRequest') && !$event->isMainRequest()) {
            return;
        }

        if (method_exists($event, 'isMasterRequest') && !$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');
        $path = $request->getPathInfo();

        if ($this->isAllowedWhileClosed($route, $path)) {
            return;
        }

        if ($this->settings->getValue(self::PUBLIC_ENABLED, '1') === '1') {
            return;
        }

        $message = (string) $this->settings->getValue(
            self::OFFLINE_MESSAGE,
            'HaloGari est en préparation. La plateforme ouvrira prochainement au public.'
        );

        $event->setResponse(new Response(
            $this->twig->render('offline/index.html.twig', [
                'offlineMessage' => $message,
            ]),
            Response::HTTP_SERVICE_UNAVAILABLE,
            [
                'Retry-After' => '3600',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]
        ));
    }

    private function isAllowedWhileClosed(string $route, string $path): bool
    {
        if ($route === '') {
            return true;
        }

        if (strpos($route, 'admin_') === 0 || strpos($route, 'api_') === 0) {
            return true;
        }

        if (in_array($route, ['app_login', 'app_logout', 'app_robots', 'app_sitemap'], true)) {
            return true;
        }

        foreach (['/admin', '/api', '/connexion', '/logout', '/robots.txt', '/sitemap.xml', '/_wdt', '/_profiler'] as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
