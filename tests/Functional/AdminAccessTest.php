<?php

namespace App\Tests\Functional;

use App\Entity\net\exelearning\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminAccessTest extends WebTestCase
{
    private function createUser(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email, string $password, array $roles): User
    {
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get('security.password_hasher');

        // Reuse existing user if present to avoid unique constraint errors
        $repo = $em->getRepository(User::class);
        $user = $repo->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setUserId(explode('@', $email)[0]);
        }
        $user->setIsLopdAccepted(true);
        $user->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function test_admin_requires_authentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        $this->assertTrue($client->getResponse()->isRedirection());
        $this->assertStringContainsString('/login', $client->getResponse()->headers->get('Location', ''));
    }

    public function test_admin_forbidden_for_non_admin(): void
    {
        $client = static::createClient();
        $user = $this->createUser($client, 'user@example.com', 'test1234', ['ROLE_USER']);
        $client->loginUser($user);

        $client->request('GET', '/admin');
        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function test_admin_accessible_for_admin(): void
    {
        $client = static::createClient();
        $admin = $this->createUser($client, 'admin@example.com', 'test1234', ['ROLE_ADMIN']);
        $client->followRedirects(true);
        $client->loginUser($admin);

        $client->request('GET', '/admin');
        $this->assertTrue($client->getResponse()->isSuccessful());
    }
}
