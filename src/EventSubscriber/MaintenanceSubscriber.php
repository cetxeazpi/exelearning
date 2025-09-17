<?php

namespace App\EventSubscriber;

use App\Service\net\exelearning\Service\SystemPreferencesService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

class MaintenanceSubscriber implements EventSubscriberInterface
{
    private const EXCLUDED_PREFIXES = [
        '/login',
        '/api',
        '/_profiler',
        '/_wdt',
        '/build',
        '/assets',
        '/health',
    ];

    public function __construct(
        private readonly SystemPreferencesService $prefs,
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Run before the router so we can intercept early
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->isExcluded($request)) {
            return;
        }

        $enabled = (bool) $this->prefs->get('maintenance.enabled', false);
        if (!$enabled) {
            return;
        }

        // Admins can use the app during maintenance
        if ($this->authChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $message = $this->prefs->get('maintenance.message', null);
        $until = $this->prefs->get('maintenance.until', null);

        $content = $this->twig->render('security/error.html.twig', [
            'error' => $message,
            'maintenanceMode' => true,
        ]);

        $response = new Response($content, Response::HTTP_SERVICE_UNAVAILABLE);

        if ($until instanceof \DateTimeInterface) {
            $response->headers->set('Retry-After', (string) $until->getTimestamp());
        }

        $event->setResponse($response);
    }

    private function isExcluded(Request $request): bool
    {
        $path = $request->getPathInfo();
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    // No per-entity getter; values come from SystemPreferencesService
}
