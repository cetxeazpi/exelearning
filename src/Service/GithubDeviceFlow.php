<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GithubDeviceFlow implements GithubDeviceFlowInterface
{
    private string $deviceCodeUrl = 'https://github.com/login/device/code';
    private string $tokenUrl = 'https://github.com/login/oauth/access_token';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId,
    ) {
    }

    public function start(string $scope = 'read:user public_repo'): array
    {
        $resp = $this->httpClient->request('POST', $this->deviceCodeUrl, [
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'client_id' => $this->clientId,
                'scope' => $scope,
            ],
        ]);

        return $resp->toArray(false);
    }

    public function poll(string $deviceCode): array
    {
        $resp = $this->httpClient->request('POST', $this->tokenUrl, [
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'client_id' => $this->clientId,
                'device_code' => $deviceCode,
                'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            ],
        ]);

        return $resp->toArray(false);
    }
}
