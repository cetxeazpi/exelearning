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
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/vnd.github+json',
        ];

        // 1) Check if Pages is already enabled
        try {
            $get = $this->httpClient->request('GET', $url, ['headers' => $headers]);
            $code = $get->getStatusCode();
            if ($code >= 200 && $code < 300) {
                // Already enabled -> try to update source (best-effort)
                try {
                    $this->httpClient->request('PUT', $url, [
                        'headers' => $headers,
                        'json' => ['source' => ['branch' => $branch, 'path' => $path]],
                    ]);
                } catch (\Throwable $e) {
                    // ignore
                }

                return ['enabled' => true];
            }
        } catch (\Throwable $e) {
            // Not enabled (likely 404) -> proceed to enable
        }

        // 2) Try to enable Pages
        try {
            $post = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => ['source' => ['branch' => $branch, 'path' => $path]],
            ]);
            $status = $post->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return ['enabled' => true];
            }
            // Non-2xx (e.g., 409/422): try to update existing config
            $put = $this->httpClient->request('PUT', $url, [
                'headers' => $headers,
                'json' => ['source' => ['branch' => $branch, 'path' => $path]],
            ]);
            $code = $put->getStatusCode();
            if ($code >= 200 && $code < 300) {
                return ['enabled' => true];
            }
        } catch (\Throwable $e) {
            // If enabling failed (network/transport), try to update config instead
            try {
                $put = $this->httpClient->request('PUT', $url, [
                    'headers' => $headers,
                    'json' => ['source' => ['branch' => $branch, 'path' => $path]],
                ]);
                $code = $put->getStatusCode();
                if ($code >= 200 && $code < 300) {
                    return ['enabled' => true];
                }
            } catch (\Throwable $e2) {
                // fall through to manual
            }
        }

        return ['enabled' => false, 'manual' => true];
    }

    public function getPagesUrl(string $owner, string $repo): string
    {
        return sprintf('https://%s.github.io/%s/', $owner, $repo);
    }
}
