<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PagesEnabler
{
    public function __construct(private readonly HttpClientInterface $httpClient, private readonly string $apiBase)
    {
    }

    public function enablePages(string $token, string $owner, string $repo, string $branch, string $path = '/'): array
    {
        $url = rtrim($this->apiBase, '/')."/repos/{$owner}/{$repo}/pages";
        $payload = [
            'source' => [
                'branch' => $branch,
                'path' => $path,
            ],
        ];
        try {
            $resp = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/vnd.github+json',
                ],
                'json' => $payload,
            ]);
            $status = $resp->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return ['enabled' => true];
            }
            // 409 or 422 means maybe already enabled or protected; try update
        } catch (\Throwable $e) {
            // ignore, fallback to manual instructions
        }
        return ['enabled' => false, 'manual' => true];
    }

    public function getPagesUrl(string $owner, string $repo): string
    {
        return sprintf('https://%s.github.io/%s/', $owner, $repo);
    }
}

