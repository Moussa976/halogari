<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AdminHostSubscriber implements EventSubscriberInterface
{
    private const PUBLIC_HOSTS = ['halogari.yt', 'www.halogari.yt'];
    private const ADMIN_HOST = 'admin.halogari.yt';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 32],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $host = strtolower($request->getHost());
        $path = $request->getPathInfo();

        if (in_array($host, self::PUBLIC_HOSTS, true) && str_starts_with($path, '/admin')) {
            $event->setResponse(new RedirectResponse('https://' . self::ADMIN_HOST . $request->getRequestUri(), 301));

            return;
        }

        if ($host === self::ADMIN_HOST && ($path === '' || $path === '/')) {
            $event->setResponse(new RedirectResponse('/admin', 302));
        }
    }
}
