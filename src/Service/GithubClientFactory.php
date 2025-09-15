<?php

namespace App\Service;

use Github\Client as GithubClient;
use Symfony\Component\HttpClient\HttpClient;

class GithubClientFactory
{
    public function __construct(private readonly string $apiBase)
    {
    }

    public function createAuthenticatedClient(string $accessToken): GithubClient
    {
        $httpClient = HttpClient::create();
        $client = new GithubClient($httpClient);
        if ($this->apiBase) {
            $client->getHttpClient()->setOption('base_uri', $this->apiBase);
        }
        $client->authenticate($accessToken, null, GithubClient::AUTH_ACCESS_TOKEN);

        return $client;
    }
}

