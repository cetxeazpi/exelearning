<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class NetlifyDeployer
{
    public function __construct(private readonly HttpClientInterface $httpClient, private readonly string $apiBase)
    {
    }

    public function listSites(string $token): array
    {
        $url = rtrim($this->apiBase, '/').'/api/v1/sites';
        $resp = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ],
        ]);

        return $resp->toArray(false);
    }

    public function deployZip(string $token, string $siteId, string $zipPath): array
    {
        $url = rtrim($this->apiBase, '/').'/api/v1/sites/'.rawurlencode($siteId).'/deploys';
        $resp = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/zip',
            ],
            'body' => fopen($zipPath, 'rb'),
        ]);

        return $resp->toArray(false);
    }

    public function createSite(string $token, ?string $name = null): array
    {
        $url = rtrim($this->apiBase, '/').'/api/v1/sites';
        $payload = [];
        if ($name) {
            $payload['name'] = $name;
        }
        $resp = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        return $resp->toArray(false);
    }
}
