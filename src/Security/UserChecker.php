<?php

namespace App\Security;

use App\Entity\net\exelearning\Entity\User;
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

        // Allow admins to authenticate even if the account is disabled
        // (used for break-glass access during incidents/maintenance)
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        if (!$user->getIsActive()) {
            // Shown on the login form for non-admins with disabled accounts
            throw new CustomUserMessageAccountStatusException('User account is disabled.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No-op
    }
}
