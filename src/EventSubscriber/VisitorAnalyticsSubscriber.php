<?php

namespace App\EventSubscriber;

use App\Service\VisitorAnalyticsTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class VisitorAnalyticsSubscriber implements EventSubscriberInterface
{
    private VisitorAnalyticsTracker $tracker;

    public function __construct(VisitorAnalyticsTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -128],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (method_exists($event, 'isMainRequest') && !$event->isMainRequest()) {
            return;
        }

        if (method_exists($event, 'isMasterRequest') && !$event->isMasterRequest()) {
            return;
        }

        $this->tracker->track($event->getRequest(), $event->getResponse());
    }
}
