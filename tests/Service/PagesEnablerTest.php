<?php

namespace App\Tests\Service;

use App\Service\PagesEnabler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PagesEnablerTest extends TestCase
{
    public function testEnablePagesSuccess(): void
    {
        $responses = [new MockResponse('', ['http_code' => 201])];
        $client = new MockHttpClient($responses);
        $enabler = new PagesEnabler($client, 'https://api.github.com');
        $res = $enabler->enablePages('token', 'owner', 'repo', 'gh-pages', '/');
        $this->assertTrue($res['enabled']);
        $this->assertSame('https://owner.github.io/repo/', $enabler->getPagesUrl('owner', 'repo'));
    }

    public function testEnablePagesFallback(): void
    {
        $responses = [new MockResponse('', ['http_code' => 403])];
        $client = new MockHttpClient($responses);
        $enabler = new PagesEnabler($client, 'https://api.github.com');
        $res = $enabler->enablePages('token', 'owner', 'repo', 'gh-pages', '/');
        $this->assertFalse($res['enabled']);
        $this->assertTrue($res['manual']);
    }
}

