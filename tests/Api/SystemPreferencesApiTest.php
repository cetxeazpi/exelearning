<?php

namespace App\Tests\Api;

use App\Entity\net\exelearning\Entity\User;
use App\Service\net\exelearning\Service\SystemPreferencesService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SystemPreferencesApiTest extends WebTestCase
{
    private function createUser(KernelBrowser $client, bool $admin = false): User
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $hasher = $client->getContainer()->get('security.password_hasher');

        $u = new User();
        $u->setEmail(sprintf('prefs+%s@example.com', uniqid()));
        $u->setUserId('u'.uniqid());
        $u->setIsLopdAccepted(true);
        $u->setRoles($admin ? ['ROLE_ADMIN'] : ['ROLE_USER']);
        $u->setPassword($hasher->hashPassword($u, 'secret'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    public function test_admin_can_list_and_get_and_put(): void
    {
        $client = static::createClient();
        $admin = $this->createUser($client, true);
        $client->loginUser($admin);

        // Seed a value
        $client->getContainer()->get(SystemPreferencesService::class)->set('maintenance.enabled', true, 'bool', 'tests');

        // List
        $client->request('GET', '/api/v2/system-preferences', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseIsSuccessful();
        $list = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($list);
        $this->assertNotEmpty($list);

        // Get one
        $client->request('GET', '/api/v2/system-preferences/maintenance.enabled', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('maintenance.enabled', $data['key']);
        $this->assertTrue((bool) $data['value']);

        // Update via PUT
        $client->request('PUT', '/api/v2/system-preferences/maintenance.enabled', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'key' => 'maintenance.enabled',
            'value' => false,
            'type' => 'bool',
        ]));
        $this->assertResponseIsSuccessful();
        $updated = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('maintenance.enabled', $updated['key']);
        $this->assertFalse((bool) $updated['value']);
    }

    public function test_non_admin_forbidden(): void
    {
        $client = static::createClient();
        $user = $this->createUser($client, false);
        $client->loginUser($user);

        $client->request('GET', '/api/v2/system-preferences', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(403);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($body);
        $this->assertSame(403, $body['status'] ?? null);
        $this->assertArrayNotHasKey('trace', $body, '403 response must not include stack trace');

        $client->request('GET', '/api/v2/system-preferences/maintenance.enabled', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(403);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($body);
        $this->assertArrayNotHasKey('trace', $body, '403 response must not include stack trace');
    }
}
