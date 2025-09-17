<?php

namespace App\Tests\Functional;

use App\Entity\net\exelearning\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminPanelTest extends WebTestCase
{
    private function ensureAdminUser(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): User
    {
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get('security.password_hasher');

        $repo = $em->getRepository(User::class);
        $email = 'admin+panel@example.com';
        $admin = $repo->findOneBy(['email' => $email]);
        if (!$admin) {
            $admin = new User();
            $admin->setEmail($email);
            $admin->setUserId('admin-panel');
        }
        $admin->setIsLopdAccepted(true);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'secret'));

        $em->persist($admin);
        $em->flush();

        return $admin;
    }

    public function testDashboardLoadsForAdmin(): void
    {
        $client = static::createClient();
        $admin = $this->ensureAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin');
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testUserCrudIndexLoads(): void
    {
        $client = static::createClient();
        $admin = $this->ensureAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', "/admin/user");
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testUserCrudNewFormLoads(): void
    {
        $client = static::createClient();
        $admin = $this->ensureAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', "/admin/user/new");
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testMaintenancePageLoads(): void
    {
        $client = static::createClient();
        $admin = $this->ensureAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', "/admin/system-preferences?prefix=maintenance.");
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testAdditionalHtmlPageLoads(): void
    {
        $client = static::createClient();
        $admin = $this->ensureAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', "http://localhost:8080/admin/system-preferences?prefix=additional_html.");
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }
}
