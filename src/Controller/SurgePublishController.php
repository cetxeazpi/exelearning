<?php

namespace App\Controller;

use App\Constants;
use App\Entity\net\exelearning\Entity\User as AppUser;
use App\Service\net\exelearning\Service\Api\OdeExportServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SurgePublishController extends AbstractController
{
    public function __construct(private readonly OdeExportServiceInterface $exportService)
    {
    }

    #[Route('/publish/surge', name: 'publish_surge_modal', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function modal(): Response
    {
        return $this->render('workarea/modals/pages/publishtosurge.html.twig');
    }

    #[Route('/api/publish/surge/export', name: 'api_surge_export', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function export(Request $request): JsonResponse
    {
        /** @var AppUser $user */
        $user = $this->getUser();
        $data = $request->toArray();
        $odeSessionId = (string) ($data['odeSessionId'] ?? '');
        if (!$odeSessionId) {
            return $this->json(['error' => 'odeSessionId required'], 400);
        }

        $res = $this->exportService->export(
            $user,
            $user,
            (string) $odeSessionId,
            false,
            Constants::EXPORT_TYPE_HTML5,
            false,
            false,
        );
        if (!isset($res['responseMessage']) || 'OK' !== $res['responseMessage']) {
            return $this->json(['error' => 'Export failed'], 500);
        }

        return $this->json([
            'ok' => true,
            'zipUrl' => $res['urlZipFile'] ?? null,
            'name' => $res['exportProjectName'] ?? null,
        ]);
    }
}
