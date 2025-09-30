<?php

namespace App\Controller\net\exelearning\Controller\Publish;

use App\Constants;
use App\Entity\net\exelearning\Entity\User as AppUser;
use App\Helper\net\exelearning\Helper\FileHelper;
use App\Service\CloudflareDeployer;
use App\Service\net\exelearning\Service\Api\OdeExportServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CloudflarePublishController extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly OdeExportServiceInterface $exportService,
        private readonly FileHelper $fileHelper,
        private readonly CloudflareDeployer $cf,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/publish/cfpages', name: 'publish_cfpages_modal', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function modal(): Response
    {
        return $this->render('workarea/modals/pages/publishtocloudflare.html.twig');
    }

    private function getToken(): ?string
    {
        return (string) $this->requestStack->getSession()?->get('cf_access_token');
    }

    #[Route('/api/publish/cf/status', name: 'api_cf_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(): JsonResponse
    {
        $sess = $this->requestStack->getSession();
        $has = (bool) $sess?->has('cf_access_token');

        return $this->json(['connected' => $has]);
    }

    #[Route('/api/publish/cf/token', name: 'api_cf_token', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveToken(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $token = (string) ($payload['access_token'] ?? '');
        if (!$token) {
            return $this->json(['error' => 'access_token required'], 400);
        }
        $this->requestStack->getSession()?->set('cf_access_token', $token);

        return $this->json(['ok' => true]);
    }

    #[Route('/api/publish/cf/accounts', name: 'api_cf_accounts', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function accounts(): JsonResponse
    {
        $token = $this->getToken();
        if (!$token) {
            return $this->json(['error' => 'Not connected'], 401);
        }
        $accs = $this->cf->listAccounts($token);
        $list = [];
        foreach ($accs as $a) {
            $list[] = ['id' => $a['id'] ?? null, 'name' => $a['name'] ?? null];
        }

        return $this->json($list);
    }

    #[Route('/api/publish/cf/projects', name: 'api_cf_projects', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function projects(Request $request): JsonResponse
    {
        $token = $this->getToken();
        if (!$token) {
            return $this->json(['error' => 'Not connected'], 401);
        }
        $accountId = (string) $request->query->get('account');
        if (!$accountId) {
            return $this->json(['error' => 'account required'], 400);
        }
        $projs = $this->cf->listProjects($token, $accountId);
        $list = [];
        foreach ($projs as $p) {
            $list[] = [
                'name' => $p['name'] ?? null,
                'subdomain' => $p['subdomain'] ?? null,
                'production_branch' => $p['production_branch'] ?? ($p['deployment_configs']['production']['branch'] ?? null),
            ];
        }

        return $this->json($list);
    }

    #[Route('/api/publish/cf/projects', name: 'api_cf_projects_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createProject(Request $request): JsonResponse
    {
        $token = $this->getToken();
        if (!$token) {
            return $this->json(['error' => 'Not connected'], 401);
        }
        $data = $request->toArray();
        $accountId = (string) ($data['account'] ?? '');
        $name = (string) ($data['name'] ?? '');
        if (!$accountId || !$name) {
            return $this->json(['error' => 'account/name required'], 400);
        }
        $proj = $this->cf->createProject($token, $accountId, $name);

        return $this->json(['name' => $proj['name'] ?? $name], 201);
    }

    #[Route('/api/publish/cf/publish', name: 'api_cf_publish', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function publish(Request $request): JsonResponse
    {
        $token = $this->getToken();
        if (!$token) {
            return $this->json(['error' => 'Not connected'], 401);
        }

        $data = $request->toArray();
        $accountId = (string) ($data['account'] ?? '');
        $project = (string) ($data['project'] ?? '');
        $odeSessionId = (string) ($data['odeSessionId'] ?? '');
        $production = (bool) ($data['production'] ?? true);
        $branch = (string) ($data['branch'] ?? 'main');
        if (!$accountId || !$project || !$odeSessionId) {
            return $this->json(['error' => 'account/project/odeSessionId required'], 400);
        }

        /** @var AppUser $dbUser */
        $dbUser = $this->getUser();
        $export = $this->exportService->export(
            $dbUser,
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
        $exportDir = rtrim((string) $this->fileHelper->getOdeSessionUserTmpExportDir($odeSessionId, $dbUser), '/');
        $zipPath = $exportDir.'-cfpages.zip';
        if (!$this->zipDirectory($exportDir, $zipPath)) {
            return $this->json(['error' => 'Zip creation failed'], 500);
        }

        try {
            $deploy = $this->cf->deployZip($token, $accountId, $project, $zipPath, $production, $branch);
        } catch (\Throwable $e) {
            $this->logger->error('Cloudflare deploy failed: '.$e->getMessage(), ['exception' => $e]);

            return $this->json(['error' => 'Cloudflare deploy failed: '.$e->getMessage()], 502);
        } finally {
            @unlink($zipPath);
        }

        $url = $deploy['url'] ?? ($deploy['deployment_trigger'] ?? null);

        return $this->json(['ok' => true, 'url' => $url]);
    }

    private function zipDirectory(string $sourceDir, string $zipPath): bool
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            return false;
        }
        $sourceDir = rtrim($sourceDir, '/');
        $baseLen = strlen($sourceDir) + 1;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $pathName = $file->getPathname();
                $rel = substr($pathName, $baseLen);
                $zip->addFile($pathName, $rel);
            }
        }

        return $zip->close();
    }
}
