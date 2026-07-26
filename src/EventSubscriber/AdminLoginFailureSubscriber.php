<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AdminAuditLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class AdminLoginFailureSubscriber implements EventSubscriberInterface
{
    private UserRepository $userRepository;
    private AdminAuditLogger $auditLogger;

    public function __construct(UserRepository $userRepository, AdminAuditLogger $auditLogger)
    {
        $this->userRepository = $userRepository;
        $this->auditLogger = $auditLogger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $email = trim((string) $request->request->get('email', ''));

        if ($email === '') {
            return;
        }

        $targetUser = $this->userRepository->findOneBy(['email' => $email]);
        if (!$targetUser instanceof User || !$this->isAdminUser($targetUser)) {
            return;
        }

        $this->auditLogger->log(null, 'admin_login_failed', $targetUser, [
            'email' => $email,
            'raison' => mb_substr($event->getException()->getMessageKey(), 0, 180),
        ]);
    }

    private function isAdminUser(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true);
    }
}
