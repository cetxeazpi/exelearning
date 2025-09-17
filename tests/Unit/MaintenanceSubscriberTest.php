<?php

namespace App\Tests\Unit;

use App\EventSubscriber\MaintenanceSubscriber;
use App\Service\net\exelearning\Service\SystemPreferencesService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

class MaintenanceSubscriberTest extends TestCase
{
    public function test_getSubscribedEvents(): void
    {
        $events = MaintenanceSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    public function test_blocks_request_when_enabled_and_non_admin(): void
    {
        $prefs = $this->createMock(SystemPreferencesService::class);
        $prefs->method('get')->willReturnCallback(function (string $key, $default = null) {
            return match ($key) {
                'maintenance.enabled' => true,
                'maintenance.message' => 'Test maintenance',
                'maintenance.until'   => null,
                default               => $default,
            };
        });

        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(function (string $attr) {
            return match ($attr) {
                'ROLE_ADMIN' => false,
                'IS_AUTHENTICATED_FULLY', 'IS_AUTHENTICATED_REMEMBERED' => true,
                default => false,
            };
        });

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('maintenance');

        $subscriber = new MaintenanceSubscriber($prefs, $auth, $twig);

        $kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };

        $request = Request::create('/');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
        $this->assertSame(503, $event->getResponse()->getStatusCode());
    }

    public function test_ignores_excluded_paths(): void
    {
        $prefs = $this->createMock(SystemPreferencesService::class);
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(function (string $attr) {
            return match ($attr) {
                'ROLE_ADMIN' => false,
                'IS_AUTHENTICATED_FULLY', 'IS_AUTHENTICATED_REMEMBERED' => true,
                default => false,
            };
        });
        $twig = $this->createMock(Environment::class);
        $subscriber = new MaintenanceSubscriber($prefs, $auth, $twig);

        $kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };

        foreach (['/admin', '/login', '/assets/foo.css'] as $path) {
            $event = new RequestEvent($kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST);
            $subscriber->onKernelRequest($event);
            $this->assertNull($event->getResponse());
        }
    }
}
