<?php

namespace App\Controller;

use App\Service\net\exelearning\Service\SystemPreferencesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(SystemPreferencesService $prefs, AuthorizationCheckerInterface $auth): Response
    {
        // When maintenance is enabled, show error on homepage for non-admins
        $enabled = (bool) $prefs->get('maintenance.enabled', false);
        if ($enabled && !$auth->isGranted('ROLE_ADMIN')) {
            $content = $this->renderView('security/error.html.twig', [
                'error' => $prefs->get('maintenance.message', null),
                'maintenanceMode' => true,
            ]);

            return new Response($content, Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // Otherwise, send visitors to the login page
        return $this->redirectToRoute('app_login');
    }
}
