<?php

namespace App\Service;

use Github\Client as GithubClient;

class GithubClientFactory
{
    public function __construct(private readonly string $apiBase)
    {
    }

    public function createAuthenticatedClient(string $accessToken): object
    {
        // Create default GitHub client with its own Builder
        $client = new GithubClient();
        $client->authenticate($accessToken, null, GithubClient::AUTH_ACCESS_TOKEN);

        return $client;
    }
}
