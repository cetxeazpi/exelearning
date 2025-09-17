<?php

namespace App\Tests\Controller;

use App\Entity\net\exelearning\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GithubPublishControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private ?User $user = null;
    private string $email;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->email = sprintf('gh+%s@example.com', bin2hex(random_bytes(6)));
        $this->user = $this->createUser($this->email);
        $this->client->loginUser($this->user);
    }

    protected function tearDown(): void
    {
        if ($this->user) {
            $u = $this->em->getRepository(User::class)->findOneBy(['email' => $this->email]);
            if ($u) {
                $this->em->remove($u);
                $this->em->flush();
            }
        }
        parent::tearDown();
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            static::getContainer()->get('security.user_password_hasher')
                ->hashPassword($user, 'password123')
        );
        $user->setRoles(['ROLE_USER']);
        $user->setUserId(bin2hex(random_bytes(20)));
        $user->setIsLopdAccepted(true);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    public function testStatusReturnsOkForLoggedUser(): void
    {
        $this->client->request('GET', '/api/publish/github/status');
        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('connected', $json);
        $this->assertArrayHasKey('pagesBranch', $json);
        $this->assertFalse((bool) $json['connected']);
    }

    public function testReposForbiddenWithoutToken(): void
    {
        // No GithubAccount and no session token -> 403 expected
        $this->client->request('GET', '/api/publish/github/repos');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeviceStartReturnsUserCode(): void
    {
        // Mock device flow via interface so controller receives it
        $mockService = new class implements \App\Service\GithubDeviceFlowInterface {
            public function start(string $scope = 'read:user public_repo'): array {
                return [
                    'device_code' => 'dev-code-123',
                    'user_code' => 'ABCD-EFGH',
                    'verification_uri' => 'https://github.com/login/device',
                    'interval' => 5,
                ];
            }
            public function poll(string $deviceCode): array { return []; }
        };
        static::getContainer()->set(\App\Service\GithubDeviceFlowInterface::class, $mockService);
        $this->client->request('POST', '/api/publish/github/device/start', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['scope' => 'read:user public_repo']));
        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('ABCD-EFGH', $json['user_code'] ?? null);
        $this->assertEquals('dev-code-123', $json['device_code'] ?? null);
    }

    public function testDevicePollPendingReturns202(): void
    {
        // Mock device flow via interface for polling
        $mockService = new class implements \App\Service\GithubDeviceFlowInterface {
            public function start(string $scope = 'read:user public_repo'): array { return []; }
            public function poll(string $deviceCode): array { return ['error' => 'authorization_pending']; }
        };
        static::getContainer()->set(\App\Service\GithubDeviceFlowInterface::class, $mockService);
        $this->client->request('POST', '/api/publish/github/device/poll', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['device_code' => 'dev-code-123']));
        $this->assertResponseStatusCodeSame(202);
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('authorization_pending', $json['error'] ?? null);
    }

    public function testCreateRepoMissingNameReturns400(): void
    {
        // Provide a dummy token via DB to pass requireToken() without altering session
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $enc = static::getContainer()->get(\App\Security\TokenEncryptor::class);
        $acc = new \App\Entity\GithubAccount();
        $acc->setUser($this->user);
        $acc->setProvider('github');
        $acc->setAccessTokenEnc($enc->encrypt('dummy'));
        $em->persist($acc);
        $em->flush();

        $this->client->request(
            'POST',
            '/api/publish/github/repos',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['visibility' => 'public'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
