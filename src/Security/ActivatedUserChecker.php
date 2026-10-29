<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\{UserCheckerInterface, UserInterface};

final class ActivatedUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActivated()) {
            throw new DisabledException('Ce compte doit être activé.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void {}
}
