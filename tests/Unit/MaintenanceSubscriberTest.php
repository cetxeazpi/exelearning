<?php

namespace App\Tests\Unit;

use App\Entity\Maintenance;
use App\EventSubscriber\MaintenanceSubscriber;
use Doctrine\ORM\EntityManagerInterface;
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
        $em = $this->createMock(EntityManagerInterface::class);
        // Mock a Doctrine repository compatible with return type
        $repo = $this->getMockBuilder(\Doctrine\ORM\EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repo->method('findOneBy')->willReturn((new Maintenance())->setEnabled(true)->setMessage('Test maintenance'));

        $em->method('getRepository')->willReturn($repo);

        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('maintenance');

        $subscriber = new MaintenanceSubscriber($em, $auth, $twig);

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
        $em = $this->createMock(EntityManagerInterface::class);
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $twig = $this->createMock(Environment::class);
        $subscriber = new MaintenanceSubscriber($em, $auth, $twig);

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
