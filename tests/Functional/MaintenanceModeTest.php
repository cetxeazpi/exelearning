<?php

namespace App\Tests\Functional;

use App\Service\net\exelearning\Service\SystemPreferencesService;
use App\Entity\net\exelearning\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MaintenanceModeTest extends WebTestCase
{
    private function setMaintenance(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, bool $enabled, ?string $message = null): void
    {
        $prefs = $client->getContainer()->get(SystemPreferencesService::class);
        $prefs->set('maintenance.enabled', $enabled, 'bool', 'tests');
        $prefs->set('maintenance.message', $message, 'string', 'tests');
    }

    protected function tearDown(): void
    {
        // Ensure maintenance is disabled after each test to avoid leaking state
        if (static::getContainer()) {
            try {
                $prefs = static::getContainer()->get(SystemPreferencesService::class);
                $prefs->set('maintenance.enabled', false, 'bool', 'tests');
                $prefs->set('maintenance.message', null, 'string', 'tests');
            } catch (\Throwable) {
                // ignore in case container not available
            }
        }
        parent::tearDown();
    }

    private function createAdmin(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): User
    {
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get('security.password_hasher');
        $repo = $em->getRepository(User::class);

        $email = 'maint-admin@example.com';
        $admin = $repo->findOneBy(['email' => $email]);
        if (!$admin) {
            $admin = new User();
            $admin->setEmail($email);
            $admin->setUserId('maint-admin');
        }
        $admin->setPassword($hasher->hashPassword($admin, 'secret'));
        $admin->setIsLopdAccepted(true);
        $admin->setRoles(['ROLE_ADMIN']);
        $em->persist($admin);
        $em->flush();

        return $admin;
    }

    private function createUser(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): User
    {
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get('security.password_hasher');
        $repo = $em->getRepository(User::class);

        $email = 'maint-user@example.com';
        $user = $repo->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setUserId('maint-user');
        }
        $user->setPassword($hasher->hashPassword($user, 'secret'));
        $user->setIsLopdAccepted(true);
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function test_authenticated_user_gets_503_when_on(): void
    {
        $client = static::createClient();
        $this->setMaintenance($client, true, 'Planned maintenance');

        $user = $this->createUser($client);
        $client->loginUser($user);

        $client->request('GET', '/');
        $this->assertSame(503, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Planned maintenance', $client->getResponse()->getContent());
    }

    public function test_admin_bypasses_maintenance(): void
    {
        $client = static::createClient();
        $this->setMaintenance($client, true);
        $admin = $this->createAdmin($client);
        $client->loginUser($admin);

        $client->request('GET', '/');
        $this->assertNotSame(503, $client->getResponse()->getStatusCode());
    }

    public function test_assets_are_not_blocked(): void
    {
        $client = static::createClient();
        $this->setMaintenance($client, true);
        $client->request('GET', '/assets/nonexistent.css');
        $this->assertNotSame(503, $client->getResponse()->getStatusCode());
    }

    public function test_non_admin_can_access_login_during_maintenance(): void
    {
        $client = static::createClient();
        $this->setMaintenance($client, true, 'Planned maintenance');

        // Login page is accessible during maintenance
        $client->request('GET', '/login');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Sign in', $client->getResponse()->getContent());
    }
}
