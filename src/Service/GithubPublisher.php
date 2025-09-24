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
        // Determine base: use provided base ref or the repo default branch if it exists
        $headSha = null;
        if ($baseRef) {
            try {
                $base = $client->api('gitData')->references()->show($owner, $repo, $baseRef);
                $headSha = $base['object']['sha'] ?? null;
            } catch (\Throwable $e) {
                $headSha = null; // fallback below
            }
        }
        if (!$headSha) {
            try {
                $repoData = $client->api('repo')->show($owner, $repo);
                $default = $repoData['default_branch'] ?? 'main';
                $base = $client->api('gitData')->references()->show($owner, $repo, 'heads/'.$default);
                $headSha = $base['object']['sha'] ?? null;
            } catch (\Throwable $e) {
                $headSha = null;
            }
        }

        if ($headSha) {
            // Base commit exists -> create branch at that commit (or return if it appeared)
            try {
                $created = $client->api('gitData')->references()->create($owner, $repo, [
                    'ref' => $branchRef,
                    'sha' => $headSha,
                ]);

                return $created['object']['sha'] ?? $headSha;
            } catch (\Throwable $e) {
                // If it already exists due to a race, show and return current SHA
                $existing = $client->api('gitData')->references()->show($owner, $repo, 'heads/'.$branch);

                return $existing['object']['sha'] ?? $headSha;
            }
        }

        // Empty repository: create an initial empty commit and point the new branch to it
        $initialTree = $client->api('gitData')->trees()->create($owner, $repo, [
            'tree' => [
                // Keep empty to create an empty root tree
            ],
        ]);
        $initialCommit = $client->api('gitData')->commits()->create($owner, $repo, [
            'message' => 'Initial commit',
            'tree' => $initialTree['sha'],
        ]);
        try {
            $client->api('gitData')->references()->create($owner, $repo, [
                'ref' => $branchRef,
                'sha' => $initialCommit['sha'],
            ]);
        } catch (\Throwable $e) {
            // If created concurrently, fall back to update (use 'heads/{branch}')
            $client->api('gitData')->references()->update($owner, $repo, 'heads/'.$branch, [
                'sha' => $initialCommit['sha'],
                'force' => false,
            ]);
        }

        return $initialCommit['sha'];
    }

    public function publishTree(
        string $token,
        string $owner,
        string $repo,
        string $branch,
        string $localPath,
        string $commitMessage = 'Publish site',
        bool $forceUpdateRef = true,
    ): string {
        $client = $this->factory->createAuthenticatedClient($token);
        $branchRef = 'refs/heads/'.$branch;

        // Determine base commit and tree (if branch exists)
        $baseCommitSha = null;
        $baseTreeSha = null;
        try {
            $head = $client->api('gitData')->references()->show($owner, $repo, 'heads/'.$branch);
            $baseCommitSha = $head['object']['sha'] ?? null;
            if ($baseCommitSha) {
                $baseCommit = $client->api('gitData')->commits()->show($owner, $repo, $baseCommitSha);
                $baseTreeSha = $baseCommit['tree']['sha'] ?? null;
            }
        } catch (\Throwable $e) {
            // Branch does not exist yet. We will create the ref after creating the first commit below.
            $baseCommitSha = null;
            $baseTreeSha = null;
        }

        // Build file list to publish
        $files = $this->scanFiles($localPath);
        if (!isset($files['.nojekyll'])) {
            // Ensure .nojekyll exists (disables Jekyll processing on Pages)
            file_put_contents(rtrim($localPath, '/').'/.nojekyll', '');
            $files['.nojekyll'] = rtrim($localPath, '/').'/.nojekyll';
        }

        // Create blobs for all files
        $blobs = [];
        foreach ($files as $path => $fsPath) {
            $content = file_get_contents($fsPath);
            $blob = $client->api('gitData')->blobs()->create($owner, $repo, [
                'content' => base64_encode($content),
                'encoding' => 'base64',
            ]);
            $blobs[$path] = $blob['sha'];
        }

        // Create a new tree representing exactly the exported site (full overwrite)
        $tree = [];
        foreach ($blobs as $path => $sha) {
            $tree[] = [
                'path' => $path,
                'mode' => '100644',
                'type' => 'blob',
                'sha' => $sha,
            ];
        }

        // Build tree payload. We omit base_tree to fully replace previous contents.
        $treePayload = [
            'tree' => $tree,
        ];
        $newTree = $client->api('gitData')->trees()->create($owner, $repo, $treePayload);

        // Create commit. Include parent only if branch already existed.
        $commitPayload = [
            'message' => $commitMessage,
            'tree' => $newTree['sha'],
        ];
        if ($baseCommitSha) {
            $commitPayload['parents'] = [$baseCommitSha];
        }
        $commit = $client->api('gitData')->commits()->create($owner, $repo, $commitPayload);

        // Update or create branch reference (robust against races)
        try {
            $client->api('gitData')->references()->show($owner, $repo, 'heads/'.$branch);
            // Ref exists -> update (note: update ref path uses 'heads/{branch}', not 'refs/heads/{branch}')
            $client->api('gitData')->references()->update($owner, $repo, 'heads/'.$branch, [
                'sha' => $commit['sha'],
                'force' => $forceUpdateRef,
            ]);
        } catch (\Throwable $e) {
            // Ref may be missing; try create and if it already exists, fall back to update
            try {
                $client->api('gitData')->references()->create($owner, $repo, [
                    'ref' => $branchRef,
                    'sha' => $commit['sha'],
                ]);
            } catch (\Throwable $createEx) {
                // If another process created it meanwhile, just update it
                $client->api('gitData')->references()->update($owner, $repo, 'heads/'.$branch, [
                    'sha' => $commit['sha'],
                    'force' => $forceUpdateRef,
                ]);
            }
        }

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
