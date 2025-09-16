<?php

namespace App\EventSubscriber;

use App\Entity\Maintenance;
use Doctrine\ORM\EntityManagerInterface;
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
        '/admin',
        '/login',
        '/logout',
        '/_profiler',
        '/_wdt',
        '/build',
        '/assets',
        '/api',
        '/workarea',
        '/new_ode',
        '/edit_ode',
        '/health',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
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

        // Only enforce maintenance AFTER the user is authenticated.
        // This guarantees that unauthenticated users always reach the login page.
        if (!$this->authChecker->isGranted('IS_AUTHENTICATED_FULLY') && !$this->authChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return;
        }

        // Admins bypass maintenance
        if ($this->authChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $maintenance = $this->getMaintenance();
        if (!$maintenance->isEnabled()) {
            return;
        }

        $content = $this->twig->render('security/error.html.twig', [
            'error' => $maintenance->getMessage(),
            'maintenanceMode' => true,
        ]);

        $response = new Response($content, Response::HTTP_SERVICE_UNAVAILABLE);

        if (null !== $maintenance->getScheduledEndAt()) {
            $response->headers->set('Retry-After', (string) $maintenance->getScheduledEndAt()->getTimestamp());
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

    private function getMaintenance(): Maintenance
    {
        $repo = $this->em->getRepository(Maintenance::class);
        $maintenance = $repo->findOneBy([]);
        if (!$maintenance instanceof Maintenance) {
            $maintenance = new Maintenance();
            $this->em->persist($maintenance);
            $this->em->flush();
        }

        return $maintenance;
    }
}
