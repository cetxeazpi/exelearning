<?php

namespace App\Service;

use App\Entity\net\exelearning\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function applyPlainPassword(User $user, ?string $plainPassword): void
    {
        $plainPassword = (string) ($plainPassword ?? '');
        if ('' === trim($plainPassword)) {
            return; // nothing to do
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
    }
}
