<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isDisabled()) {
            throw new CustomUserMessageAccountStatusException('Ce compte est désactivé. Si vous pensez qu’il s’agit d’une erreur, contactez HaloGari.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
