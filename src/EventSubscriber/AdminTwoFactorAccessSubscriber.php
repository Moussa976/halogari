<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\AdminTwoFactorCodeManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

class AdminTwoFactorAccessSubscriber implements EventSubscriberInterface
{
    private Security $security;
    private AdminTwoFactorCodeManager $codeManager;

    public function __construct(Security $security, AdminTwoFactorCodeManager $codeManager)
    {
        $this->security = $security;
        $this->codeManager = $codeManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/admin/verification')
            || str_starts_with($path, '/logout')
            || str_starts_with($path, '/_profiler')
            || str_starts_with($path, '/_wdt')
        ) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->isAdmin($user) || $this->codeManager->isVerified($request, $user)) {
            return;
        }

        $event->setResponse(new RedirectResponse('/admin/verification'));
    }

    private function isAdmin(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true);
    }
}
