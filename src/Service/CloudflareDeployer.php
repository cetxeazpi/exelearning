<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CloudflareDeployer
{
    public function __construct(private readonly HttpClientInterface $httpClient, private readonly string $apiBase)
    {
    }

    public function listAccounts(string $token): array
    {
        $url = rtrim($this->apiBase, '/').'/accounts';
        $resp = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ],
        ]);
        $data = $resp->toArray(false);

        return $data['result'] ?? [];
    }

    public function listProjects(string $token, string $accountId): array
    {
        $url = rtrim($this->apiBase, '/')."/accounts/{$accountId}/pages/projects";
        $resp = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ],
        ]);
        $data = $resp->toArray(false);

        return $data['result'] ?? [];
    }

    public function createProject(string $token, string $accountId, string $name): array
    {
        $url = rtrim($this->apiBase, '/')."/accounts/{$accountId}/pages/projects";
        $payload = [
            'name' => $name,
            'production_branch' => 'main',
            'build_config' => ['build_command' => '', 'destination_dir' => '/'],
            'deployment_configs' => ['production' => ['compatibility_date' => date('Y-m-d')]],
        ];
        $resp = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);
        $data = $resp->toArray(false);

        return $data['result'] ?? [];
    }

    public function deployZip(string $token, string $accountId, string $project, string $zipPath, bool $production = true, string $branch = 'main'): array
    {
        // Direct upload API (multipart) for Pages deployments
        $url = rtrim($this->apiBase, '/')."/accounts/{$accountId}/pages/projects/{$project}/deployments";
        $form = [
            // According to API docs, send as multipart with a 'deployment' file (zip)
            'deployment' => fopen($zipPath, 'rb'),
            // Hint branch to ensure it matches production branch (promotes to production)
            'branch' => $branch,
            // Best-effort flag in case API supports explicit environment selection
            'production' => $production ? 'true' : 'false',
            // Optional metadata for better UX in CF dashboard
            'commit_message' => 'Publish from eXeLearning',
        ];
        $resp = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
            'body' => $form,
        ]);
        $data = $resp->toArray(false);

        return $data['result'] ?? [];
    }
}
