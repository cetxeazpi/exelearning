<?php

namespace App\Service;

class GithubPublisher
{
    public function __construct(
        private readonly GithubClientFactory $factory,
        private readonly PagesEnabler $pagesEnabler,
        private readonly string $defaultBranch,
    ) {
    }

    public function listUserRepositories(string $token): array
    {
        $client = $this->factory->createAuthenticatedClient($token);
        $user = $client->currentUser()->show();
        $login = $user['login'] ?? null;
        $repos = [];
        // Use user repos API
        foreach ($client->api('user')->repositories($login, 'owner') as $r) {
            $repos[] = [
                'name' => $r['name'],
                'owner' => $r['owner']['login'],
                'private' => (bool) ($r['private'] ?? false),
                'default_branch' => $r['default_branch'] ?? 'main',
            ];
        }

        return $repos;
    }

    public function listBranches(string $token, string $owner, string $repo): array
    {
        $client = $this->factory->createAuthenticatedClient($token);
        $branches = [];
        foreach ($client->api('repo')->branches($owner, $repo) as $b) {
            if (isset($b['name'])) {
                $branches[] = $b['name'];
            }
        }

        return $branches;
    }

    public function createRepository(string $token, string $name, string $visibility = 'public'): array
    {
        $client = $this->factory->createAuthenticatedClient($token);
        // Create repository for the authenticated user
        $repo = $client->api('repo')->create($name, '', '', 'private' !== $visibility);

        return [
            'name' => $repo['name'],
            'owner' => $repo['owner']['login'],
            'private' => (bool) ($repo['private'] ?? false),
        ];
    }

    public function ensureBranch(string $token, string $owner, string $repo, string $branch, ?string $baseRef = null): string
    {
        $client = $this->factory->createAuthenticatedClient($token);
        $branchRef = 'refs/heads/'.$branch;
        // If it already exists, return its head sha
        try {
            $existing = $client->api('gitData')->references()->show($owner, $repo, 'heads/'.$branch);
            if (!empty($existing['object']['sha'])) {
                return $existing['object']['sha'];
            }
        } catch (\Throwable $e) {
            // continue to create
        }
        // Determine base: use default branch head, or provided baseRef
        $headSha = null;
        if ($baseRef) {
            try {
                $base = $client->api('gitData')->references()->show($owner, $repo, $baseRef);
                $headSha = $base['object']['sha'] ?? null;
            } catch (\Throwable $e) {
                $headSha = null; // fallback below
            }
        } else {
            try {
                $repoData = $client->api('repo')->show($owner, $repo);
                $default = $repoData['default_branch'] ?? 'main';
                $base = $client->api('gitData')->references()->show($owner, $repo, 'heads/'.$default);
                $headSha = $base['object']['sha'] ?? null;
            } catch (\Throwable $e) {
                $headSha = null;
            }
        }
        if (!$headSha) {
            // Fallback for empty/new repos or when default branch head cannot be resolved
            $headSha = str_repeat('0', 40);
        }
        $created = $client->api('gitData')->references()->create($owner, $repo, [
            'ref' => $branchRef,
            'sha' => $headSha,
        ]);

        return $created['object']['sha'];
    }

    public function publishTree(string $token, string $owner, string $repo, string $branch, string $localPath, string $commitMessage = 'Publish site'): string
    {
        $client = $this->factory->createAuthenticatedClient($token);
        $branchRef = 'refs/heads/'.$branch;

        // Get HEAD of target branch (create if missing based on default branch)
        $repoData = $client->api('repo')->show($owner, $repo);
        $defaultBranch = $repoData['default_branch'] ?? 'main';
        try {
            $head = $client->api('gitData')->references()->show($owner, $repo, 'heads/'.$branch);
            $baseSha = $head['object']['sha'];
        } catch (\Throwable $e) {
            $baseSha = $this->ensureBranch($token, $owner, $repo, $branch, 'heads/'.$defaultBranch);
        }

        // Build file list
        $files = $this->scanFiles($localPath);
        if (!isset($files['.nojekyll'])) {
            // Ensure .nojekyll
            file_put_contents(rtrim($localPath, '/').'/.nojekyll', '');
            $files['.nojekyll'] = rtrim($localPath, '/').'/.nojekyll';
        }

        // Create blobs
        $blobs = [];
        foreach ($files as $path => $fsPath) {
            $content = file_get_contents($fsPath);
            $blob = $client->api('gitData')->blobs()->create($owner, $repo, [
                'content' => base64_encode($content),
                'encoding' => 'base64',
            ]);
            $blobs[$path] = $blob['sha'];
        }

        // Create tree from blobs
        $tree = [];
        foreach ($blobs as $path => $sha) {
            $tree[] = [
                'path' => $path,
                'mode' => '100644',
                'type' => 'blob',
                'sha' => $sha,
            ];
        }
        $newTree = $client->api('gitData')->trees()->create($owner, $repo, [
            'base_tree' => $baseSha,
            'tree' => $tree,
        ]);

        // Create commit
        $commit = $client->api('gitData')->commits()->create($owner, $repo, [
            'message' => $commitMessage,
            'tree' => $newTree['sha'],
            'parents' => [$baseSha],
        ]);

        // Update ref
        $client->api('gitData')->references()->update($owner, $repo, $branchRef, [
            'sha' => $commit['sha'],
            'force' => true,
        ]);

        return $commit['sha'];
    }

    public function publishAndEnablePages(string $token, string $owner, string $repo, string $localPath, ?string $branch = null, string $commitMessage = 'Publish site'): array
    {
        $branch = $branch ?: $this->defaultBranch;
        $commitSha = $this->publishTree($token, $owner, $repo, $branch, $localPath, $commitMessage);
        $pages = $this->pagesEnabler->enablePages($token, $owner, $repo, $branch, '/');
        $pagesUrl = $this->pagesEnabler->getPagesUrl($owner, $repo);

        return [
            'commit' => $commitSha,
            'pagesEnabled' => (bool) ($pages['enabled'] ?? false),
            'pagesUrl' => $pagesUrl,
        ];
    }

    private function scanFiles(string $path): array
    {
        $files = [];
        $base = rtrim($path, '/');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $fsPath = $file->getPathname();
            $rel = ltrim(str_replace($base, '', $fsPath), '/');
            $files[$rel] = $fsPath;
        }

        return $files;
    }
}
