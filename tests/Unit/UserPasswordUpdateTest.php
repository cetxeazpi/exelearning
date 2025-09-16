<?php

namespace App\Tests\Unit;

use App\Entity\net\exelearning\Entity\User;
use App\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserPasswordUpdateTest extends KernelTestCase
{
    public function test_hash_on_create_and_update_when_plain_password_present(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $userManager = $container->get(UserManager::class);

        $user = new User();
        $user->setEmail('user@test.local');
        $user->setUserId('user');
        $user->setIsLopdAccepted(true);
        $user->setRoles(['ROLE_USER']);

        $userManager->applyPlainPassword($user, 'new-password');
        $hash1 = $user->getPassword();
        $this->assertNotEmpty($hash1);

        // Re-applying a different password should change the hash
        $userManager->applyPlainPassword($user, 'other-password');
        $this->assertNotSame($hash1, $user->getPassword());
    }

    public function test_password_unchanged_when_plain_password_absent(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $userManager = $container->get(UserManager::class);

        $user = new User();
        $user->setEmail('user2@test.local');
        $user->setUserId('user2');
        $user->setIsLopdAccepted(true);
        $user->setRoles(['ROLE_USER']);
        $userManager->applyPlainPassword($user, 'initial');
        $before = $user->getPassword();

        // No change when null or empty
        $userManager->applyPlainPassword($user, null);
        $this->assertSame($before, $user->getPassword());
        $userManager->applyPlainPassword($user, '');
        $this->assertSame($before, $user->getPassword());
        $userManager->applyPlainPassword($user, '   ');
        $this->assertSame($before, $user->getPassword());
    }
}

