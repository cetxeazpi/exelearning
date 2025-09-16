<?php

namespace App\Tests\Functional;

use App\Entity\Maintenance;
use App\Entity\net\exelearning\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MaintenanceModeTest extends WebTestCase
{
    private function enableMaintenance(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, bool $enabled, ?string $message = null): void
    {
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $maintenance = $em->getRepository(Maintenance::class)->findOneBy([]) ?? new Maintenance();
        $maintenance->setEnabled($enabled);
        $maintenance->setMessage($message);
        if (null === $maintenance->getId()) {
            $em->persist($maintenance);
        }
        $em->flush();
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

    public function test_anonymous_gets_503_when_on(): void
    {
        $client = static::createClient();
        $this->enableMaintenance($client, true, 'Planned maintenance');

        $client->request('GET', '/');
        $this->assertSame(503, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Maintenance', $client->getResponse()->getContent());
    }

    public function test_admin_bypasses_maintenance(): void
    {
        $client = static::createClient();
        $this->enableMaintenance($client, true);
        $admin = $this->createAdmin($client);
        $client->loginUser($admin);

        $client->request('GET', '/');
        $this->assertNotSame(503, $client->getResponse()->getStatusCode());
    }

    public function test_assets_are_not_blocked(): void
    {
        $client = static::createClient();
        $this->enableMaintenance($client, true);
        $client->request('GET', '/assets/nonexistent.css');
        $this->assertNotSame(503, $client->getResponse()->getStatusCode());
    }
}
