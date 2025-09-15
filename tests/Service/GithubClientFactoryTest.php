<?php

namespace App\Tests\Service;

use App\Service\GithubClientFactory;
use PHPUnit\Framework\TestCase;

class GithubClientFactoryTest extends TestCase
{
    public function testCreateAuthenticatedClient(): void
    {
        $factory = new GithubClientFactory('https://api.github.com');
        $client = $factory->createAuthenticatedClient('test_token');
        $this->assertNotNull($client);
        $this->assertTrue(method_exists($client, 'authenticate'));
    }
}

