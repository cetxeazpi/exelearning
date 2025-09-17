<?php

namespace App\Controller;

use App\Constants;
use App\Entity\GithubAccount;
use App\Entity\net\exelearning\Entity\User as AppUser;
use App\Helper\net\exelearning\Helper\FileHelper;
use App\Security\TokenEncryptor;
use App\Service\GithubClientFactory;
use App\Service\GithubDeviceFlowInterface;
use App\Service\GithubPublisher;
use App\Service\net\exelearning\Service\Api\OdeExportServiceInterface;
use App\Service\PagesEnabler;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GithubPublishController extends AbstractController
{
    public function __construct(
        private readonly ClientRegistry $clients,
        private readonly EntityManagerInterface $em,
        private readonly TokenEncryptor $encryptor,
        private readonly GithubPublisher $publisher,
        private readonly GithubClientFactory $clientFactory,
        private readonly PagesEnabler $pagesEnabler,
        private readonly GithubDeviceFlowInterface $deviceFlow,
        private readonly OdeExportServiceInterface $exportService,
        private readonly FileHelper $fileHelper,
        private readonly string $pagesBranch,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/publish/github', name: 'publish_github_modal', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function modal(): Response
    {
        return $this->render('workarea/modals/pages/publishtogithub.html.twig');
    }

    #[Route('/oauth/github/connect', name: 'oauth_github_connect')]
    #[IsGranted('ROLE_USER')]
    public function connect(Request $request): RedirectResponse
    {
        $scopes = ['read:user', 'public_repo'];
        // Mark popup flow in session if requested
        if ($request->query->getBoolean('popup')) {
            $this->requestStack->getSession()?->set('github_popup', true);
        }

        return $this->clients->getClient('github')->redirect($scopes);
    }

    #[Route('/oauth/github/check', name: 'oauth_github_check')]
    public function oauthCallback(Request $request): Response
    {
        $client = $this->clients->getClient('github');
        $sess = $this->requestStack->getSession();
        $accessToken = null;
        $githubUser = null;

        // Step 1: obtain access token
        try {
            $accessToken = $client->getAccessToken();
        } catch (\Throwable $e) {
            if ($sess?->remove('github_popup')) {
                return $this->render('oauth/github_popup_close.html.twig', [
                    'ok' => false,
                    'error' => $e->getMessage(),
                    'session_id' => $sess?->getId(),
                    'session_has_token' => (bool) $sess?->has('github_access_token'),
                ]);
            }
            throw $e;
        }

        // Step 2: store token in session immediately (so UI can proceed)
        $sess?->set('github_access_token', $accessToken->getToken());
        $this->logger->info('GitHub OAuth: session token stored', [
            'sessionId' => $sess?->getId(),
            'tokenLen' => strlen((string) $accessToken->getToken()),
        ]);

        // Step 3: try to fetch user profile (best-effort)
        try {
            /** @var GithubResourceOwner $githubUser */
            $githubUser = $client->fetchUserFromToken($accessToken);
        } catch (\Throwable $e) {
            $this->logger->warning('GitHub OAuth: fetch user failed (continuing with session token only): '.$e->getMessage());
        }

        // Step 4: if we have an authenticated app user and a GitHub profile, persist the link
        try {
            /** @var AppUser|null $user */
            $user = $this->getUser();
            if ($user && $githubUser) {
                $repo = $this->em->getRepository(GithubAccount::class);
                $link = $repo->findOneBy(['user' => $user]);
                if (!$link) {
                    $link = new GithubAccount();
                    $link->setUser($user);
                    $this->em->persist($link);
                }
                $link->setProvider('github');
                $link->setGithubLogin($githubUser->getNickname());
                $link->setGithubId((string) $githubUser->getId());
                $link->setAccessTokenEnc($this->encryptor->encrypt($accessToken->getToken()));
                $link->setRefreshTokenEnc($this->encryptor->encrypt($accessToken->getRefreshToken()));
                $expires = $accessToken->getExpires();
                $link->setTokenExpiresAt($expires ? new \DateTimeImmutable('@'.$expires) : null);
                $this->em->flush();
                $this->logger->info('GitHub OAuth: DB link persisted', [
                    'githubLogin' => $githubUser->getNickname(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('GitHub OAuth callback DB persist failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        }

        // Step 5: close popup and notify opener
        if ($sess?->remove('github_popup')) {
            return $this->render('oauth/github_popup_close.html.twig', [
                'ok' => true,
                'session_id' => $sess?->getId(),
                'session_has_token' => (bool) $sess?->has('github_access_token'),
            ]);
        }

        return $this->render('workarea/modals/pages/publishtogithub.html.twig');
    }

    private function requireToken(): string
    {
        /** @var AppUser $user */
        $user = $this->getUser();
        $repo = $this->em->getRepository(GithubAccount::class);
        /** @var GithubAccount|null $acc */
        $acc = $repo->findOneBy(['user' => $user]);
        if (!$acc) {
            // Fallback to a token stored in session (immediately after OAuth)
            $sessionToken = $this->requestStack->getSession()?->get('github_access_token');
            if ($sessionToken) {
                return (string) $sessionToken;
            }
            throw $this->createAccessDeniedException('GitHub not connected');
        }
        $token = $this->encryptor->decrypt($acc->getAccessTokenEnc());
        if (!$token) {
            throw $this->createAccessDeniedException('GitHub token missing');
        }

        return $token;
    }

    #[Route('/api/publish/github/repos', name: 'api_github_repos', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listRepos(): JsonResponse
    {
        $token = $this->requireToken();
        $repos = $this->publisher->listUserRepositories($token);

        return $this->json($repos);
    }

    #[Route('/api/publish/github/branches', name: 'api_github_branches', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listBranches(Request $request): JsonResponse
    {
        $owner = (string) $request->query->get('owner', '');
        $repo = (string) $request->query->get('repo', '');
        if ('' === $owner || '' === $repo) {
            return $this->json(['error' => 'owner/repo required'], 400);
        }
        $token = $this->requireToken();
        $branches = $this->publisher->listBranches($token, $owner, $repo);

        return $this->json($branches);
    }

    #[Route('/api/publish/github/status', name: 'api_github_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(): JsonResponse
    {
        /** @var AppUser $user */
        $user = $this->getUser();
        $repo = $this->em->getRepository(GithubAccount::class);
        /** @var GithubAccount|null $acc */
        $acc = $repo->findOneBy(['user' => $user]);

        $session = $this->requestStack->getSession();
        $sessionToken = $session?->get('github_access_token');
        $connected = false;
        $source = 'none';
        $login = null;
        $expiresAt = null;
        $dbRow = false;
        $encPresent = false;
        $encLen = 0;
        $sessionId = $session?->getId();
        $sessionHasToken = !empty($sessionToken);

        if ($acc) {
            $dbRow = true;
            $enc = $acc->getAccessTokenEnc();
            $encPresent = !empty($enc);
            $encLen = $enc ? strlen($enc) : 0;
            $token = $this->encryptor->decrypt($acc->getAccessTokenEnc());
            if ($token) {
                $connected = true;
                $source = 'db';
                $login = $acc->getGithubLogin();
                $expiresAt = $acc->getTokenExpiresAt()?->getTimestamp();
            }
        }
        if (!$connected && $sessionToken) {
            $connected = true;
            $source = 'session';
        }

        return $this->json([
            'connected' => $connected,
            'source' => $source,
            'login' => $login,
            'tokenExpiresAt' => $expiresAt,
            'pagesBranch' => $this->pagesBranch,
            // debug hints (no secrets):
            'dbRow' => $dbRow,
            'encPresent' => $encPresent,
            'encLen' => $encLen,
            'sessionId' => $sessionId,
            'sessionHasToken' => $sessionHasToken,
        ]);
    }

    #[Route('/api/publish/github/device/start', name: 'api_github_device_start', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deviceStart(Request $request): JsonResponse
    {
        $scope = (string) ($request->toArray()['scope'] ?? 'read:user public_repo');
        $data = $this->deviceFlow->start($scope);

        return $this->json($data);
    }

    #[Route('/api/publish/github/device/poll', name: 'api_github_device_poll', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function devicePoll(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $deviceCode = (string) ($payload['device_code'] ?? '');
        if (!$deviceCode) {
            return $this->json(['error' => 'device_code required'], 400);
        }
        $res = $this->deviceFlow->poll($deviceCode);
        if (!empty($res['access_token'])) {
            // Save token
            /** @var AppUser $user */
            $user = $this->getUser();
            $repo = $this->em->getRepository(GithubAccount::class);
            $link = $repo->findOneBy(['user' => $user]) ?? new GithubAccount();
            $link->setUser($user);
            $link->setProvider('github');
            $link->setAccessTokenEnc($this->encryptor->encrypt($res['access_token']));
            $link->setTokenExpiresAt(null);
            $this->em->persist($link);
            $this->em->flush();

            return $this->json(['ok' => true]);
        }

        // pending: {error: authorization_pending}, slow_down, expired_token, etc.
        return $this->json($res, 202);
    }

    #[Route('/api/publish/github/repos', name: 'api_github_create_repo', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createRepo(Request $request): JsonResponse
    {
        $token = $this->requireToken();
        $data = json_decode($request->getContent(), true) ?: [];
        $name = (string) ($data['name'] ?? '');
        $visibility = (string) ($data['visibility'] ?? 'public');
        if ('' === $name) {
            return $this->json(['error' => 'Missing name'], 400);
        }
        $repo = $this->publisher->createRepository($token, $name, $visibility);

        return $this->json($repo, 201);
    }

    #[Route('/api/publish/github/check', name: 'api_github_check', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkRepo(Request $request): JsonResponse
    {
        $token = $this->requireToken();
        $data = json_decode($request->getContent(), true) ?: [];
        $owner = (string) ($data['owner'] ?? '');
        $repo = (string) ($data['repo'] ?? '');
        if (!$owner || !$repo) {
            return $this->json(['error' => 'owner/repo required'], 400);
        }

        $client = $this->clientFactory->createAuthenticatedClient($token);
        $repoData = $client->api('repo')->show($owner, $repo);
        $default = $repoData['default_branch'] ?? 'main';
        $branchInput = (string) ($data['branch'] ?? '');
        $branch = '' !== $branchInput ? $branchInput : ($this->pagesBranch ?: 'gh-pages');

        $hasContent = false;
        try {
            $contents = $client->api('repo')->contents()->show($owner, $repo);
            $hasContent = is_array($contents) && count($contents) > 0;
        } catch (\Throwable $e) {
            $hasContent = false;
        }

        return $this->json([
            'defaultBranch' => $default,
            'branch' => $branch,
            'nonEmpty' => $hasContent,
        ]);
    }

    #[Route('/api/publish/github/publish', name: 'api_github_publish', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function publish(Request $request): JsonResponse
    {
        $token = $this->requireToken();
        /** @var AppUser $dbUser */
        $dbUser = $this->getUser();

        $data = json_decode($request->getContent(), true) ?: [];
        $owner = (string) ($data['owner'] ?? '');
        $repo = (string) ($data['repo'] ?? '');
        $overwrite = (bool) ($data['overwrite'] ?? false);
        $branch = $this->pagesBranch ?: 'gh-pages';
        if (!$owner || !$repo) {
            return $this->json(['error' => 'owner/repo required'], 400);
        }

        // Export to temp dir using HTML5 preview export
        $odeSessionId = (string) ($data['odeSessionId'] ?? '') ?: $this->getRequestOdeSessionId($request);
        $user = $dbUser; // The service expects both security user and db user
        $export = $this->exportService->export(
            $user,
            $dbUser,
            $odeSessionId,
            false,
            Constants::EXPORT_TYPE_HTML5,
            true,
            false,
        );
        if (!isset($export['responseMessage']) || 'OK' !== $export['responseMessage']) {
            return $this->json(['error' => 'Export failed'], 500);
        }
        $exportDir = $this->fileHelper->getOdeSessionUserTmpExportDir($odeSessionId, $dbUser);

        // Ensure target branch exists
        $client = $this->clientFactory->createAuthenticatedClient($token);
        try {
            $client->api('gitData')->references()->show($owner, $repo, 'heads/'.$branch);
        } catch (\Throwable $e) {
            $this->publisher->ensureBranch($token, $owner, $repo, $branch, null);
        }

        // Publish tree (single commit)
        $commitSha = $this->publisher->publishTree($token, $owner, $repo, $branch, rtrim($exportDir, '/'), 'Publish site');

        // Try to enable pages
        $enabled = $this->pagesEnabler->enablePages($token, $owner, $repo, $branch, '/');
        $pagesUrl = $this->pagesEnabler->getPagesUrl($owner, $repo);

        $repoUrl = sprintf('https://github.com/%s/%s', $owner, $repo);
        $resp = [
            'commit' => $commitSha,
            'pagesUrl' => $pagesUrl,
            'repoUrl' => $repoUrl,
        ];
        if (!($enabled['enabled'] ?? false)) {
            $resp['manual'] = 'Enable GitHub Pages under Settings → Pages. Source: branch '.$branch.' / root';
        }

        return $this->json($resp);
    }

    private function getRequestOdeSessionId(Request $request): string
    {
        // Try to reuse existing infrastructure to obtain current session id
        // Fallback to query/header param if provided
        $sessionId = $request->headers->get('X-ODE-SESSION') ?: $request->query->get('odeSessionId');
        if (!$sessionId && method_exists($this->getUser(), 'getUserId')) {
            // As a last resort, ask the front-end to pass it; keep empty
            $sessionId = '';
        }

        return (string) $sessionId;
    }
}
