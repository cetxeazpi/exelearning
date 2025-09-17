<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as CoreAccessDeniedException;

/**
 * Sanitize problem+json body for 403 errors: keep a concise payload without stack traces.
 *
 * Only applies to API requests (Accept contains json or path starts with /api),
 * and only for access-denied exceptions. Other exceptions are handled by the
 * default problem normalizers (useful in development).
 */
final class ApiExceptionSanitizerSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $authChecker)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Run early so default listeners don't expand the body with a trace
        return [KernelEvents::EXCEPTION => ['onKernelException', 200]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        if (!$this->isAccessDenied($e)) {
            return;
        }

        $req = $event->getRequest();
        $accept = $req->headers->get('Accept', '');
        $isApiPath = str_starts_with($req->getPathInfo(), '/api/');
        $wantsJson = false !== stripos($accept, 'json');
        if (!$isApiPath && !$wantsJson) {
            return; // let HTML error rendering handle non-API requests
        }

        // Let the default listener handle unauthenticated (302 redirect to login).
        $isAuthenticated = $this->authChecker->isGranted('IS_AUTHENTICATED_FULLY')
                           || $this->authChecker->isGranted('IS_AUTHENTICATED_REMEMBERED');
        if (!$isAuthenticated) {
            return;
        }

        $payload = [
            'title' => 'An error occurred',
            'detail' => 'Access Denied.',
            'status' => 403,
            'type' => '/errors/403',
        ];
        $event->setResponse(new JsonResponse($payload, 403));
    }

    private function isAccessDenied(\Throwable $e): bool
    {
        return $e instanceof AccessDeniedHttpException || $e instanceof CoreAccessDeniedException;
    }
}
