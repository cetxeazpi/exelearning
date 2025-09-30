<?php

namespace App\Controller\net\exelearning\Controller\Publish;

use App\Constants;
use App\Entity\net\exelearning\Entity\User as AppUser;
use App\Helper\net\exelearning\Helper\FileHelper;
use App\Service\net\exelearning\Service\Api\OdeExportServiceInterface;
use App\Service\NetlifyDeployer;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class NetlifyPublishController extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly OdeExportServiceInterface $exportService,
        private readonly FileHelper $fileHelper,
        private readonly NetlifyDeployer $netlify,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/publish/netlify', name: 'publish_netlify_modal', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function modal(): Response
    {
        return $this->render('workarea/modals/pages/publishtonetlify.html.twig');
    }

    private function getToken(): ?string
    {
        return (string) $this->requestStack->getSession()?->get('netlify_access_token');
    }

    #[Route('/api/publish/netlify/status', name: 'api_netlify_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(): JsonResponse
    {
        $sess = $this->requestStack->getSession();
        $has = (bool) $sess?->has('netlify_access_token');

        return $this->json(['connected' => $has]);
    }

    #[Route('/api/publish/netlify/token', name: 'api_netlify_token', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveToken(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $token = (string) ($payload['access_token'] ?? '');
        if (!$token) {
            return $this->json(['error' => 'access_token required'], 400);
        }
        $this->requestStack->getSession()?->set('netlify_access_token', $token);

        return $this->json(['ok' => true]);
    }

    #[Route('/api/publish/netlify/sites', name: 'api_netlify_sites', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listSites(): JsonResponse
    {
        $token = $this->getToken();
        if (!$token) {
            return $this->json(['error' => 'Not connected'], 401);
        }
        try {
            $sites = $this->netlify->listSites($token);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 0);
            if (401 === $code || 403 === $code) {
                // Token no válido o expirado: limpiar sesión y solicitar reconexión
                $this->requestStack->getSession()?->remove('netlify_access_token');

                return $this->json(['error' => 'Invalid or expired token', 'reauth' => true], 401);
            }
            $this->logger->error('Netlify listSites failed: '.$e->getMessage(), ['exception' => $e]);

            return $this->json(['error' => 'List sites failed: '.$e->getMessage()], 502);
        }

        return $this->json(array_map(function ($s) {
            return [
                'id' => $s['id'] ?? null,
                'name' => $s['name'] ?? ($s['site_id'] ?? null),
                'url' => $s['url'] ?? ($s['ssl_url'] ?? null),
            ];
        }, is_array($sites) ? $sites : []));
    }

    #[Route('/api/publish/netlify/sites', name: 'api_netlify_sites_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createSite(Request $request): JsonResponse
    {
        $token = $this->getToken();
        if (!$token) {
            return $this->json(['error' => 'Not connected'], 401);
        }
        $data = $request->toArray();
        $name = (string) ($data['name'] ?? '');
        try {
            $site = $this->netlify->createSite($token, $name ?: null);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 0);
            if (401 === $code || 403 === $code) {
                $this->requestStack->getSession()?->remove('netlify_access_token');

                return $this->json(['error' => 'Invalid or expired token', 'reauth' => true], 401);
            }

            return $this->json(['error' => 'Create site failed: '.$e->getMessage()], 502);
        }

        return $this->json([
            'id' => $site['id'] ?? null,
            'name' => $site['name'] ?? ($site['site_id'] ?? null),
            'url' => $site['url'] ?? ($site['ssl_url'] ?? null),
        ], 201);
    }

    #[Route('/api/publish/netlify/publish', name: 'api_netlify_publish', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function publish(Request $request): JsonResponse
    {
        $token = $this->getToken();
        if (!$token) {
            return $this->json(['error' => 'Not connected'], 401);
        }

        $data = $request->toArray();
        $siteId = (string) ($data['siteId'] ?? '');
        if (!$siteId) {
            return $this->json(['error' => 'siteId required'], 400);
        }

        /** @var AppUser $dbUser */
        $dbUser = $this->getUser();
        $odeSessionId = (string) ($data['odeSessionId'] ?? '');
        if (!$odeSessionId) {
            return $this->json(['error' => 'odeSessionId required'], 400);
        }

        // Export site (HTML5) to temp dir (preview)
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

        // Create zip
        $zipPath = $exportDir.'-netlify.zip';
        $ok = $this->zipDirectory($exportDir, $zipPath);
        if (!$ok) {
            return $this->json(['error' => 'Zip creation failed'], 500);
        }

        try {
            $deploy = $this->netlify->deployZip($token, $siteId, $zipPath);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 0);
            if (401 === $code || 403 === $code) {
                $this->requestStack->getSession()?->remove('netlify_access_token');

                return $this->json(['error' => 'Invalid or expired token', 'reauth' => true], 401);
            }
            $this->logger->error('Netlify deploy failed: '.$e->getMessage(), ['exception' => $e]);

            return $this->json(['error' => 'Netlify deploy failed: '.$e->getMessage()], 502);
        } finally {
            @unlink($zipPath);
        }

        $siteUrl = $deploy['ssl_url'] ?? ($deploy['url'] ?? null);

        return $this->json([
            'ok' => true,
            'siteUrl' => $siteUrl,
        ]);
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
