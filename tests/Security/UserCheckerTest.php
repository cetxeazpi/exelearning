<?php

namespace App\Tests\Security;

use App\Entity\net\exelearning\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UserCheckerTest extends TestCase
{
    public function testAllowsActiveUser(): void
    {
        $user = new User();
        $user->setEmail('active@example.com');
        $user->setPassword('x');
        $user->setRoles(['ROLE_USER']);

        // Force isActive=true (normally set on prePersist)
        $r = new \ReflectionProperty($user, 'isActive');
        $r->setAccessible(true);
        $r->setValue($user, true);

        $checker = new UserChecker();
        $checker->checkPreAuth($user); // should not throw
        $this->assertTrue(true);
    }

    public function testBlocksInactiveUser(): void
    {
        $user = new User();
        $user->setEmail('disabled@example.com');
        $user->setPassword('x');
        $user->setRoles(['ROLE_USER']);

        // Force isActive=false
        $r = new \ReflectionProperty($user, 'isActive');
        $r->setAccessible(true);
        $r->setValue($user, false);

        $checker = new UserChecker();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('User account is disabled.');

        $checker->checkPreAuth($user);
    }
}

