<?php

namespace App\Tests\Command;

use App\Command\PrefsSyncCommand;
use App\Config\SystemPrefRegistry;
use App\Entity\net\exelearning\Entity\SystemPreferences;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PrefsSyncCommandTest extends TestCase
{
    public function testCreatesMissingKeysAndUpdatesTypes(): void
    {
        // Use the real registry to avoid mocking a final class
        $registry = new SystemPrefRegistry();

        // Mock repository: return null for all keys so command creates them
        $repo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repo->method('findOneBy')->willReturn(null);

        // Capture persisted entities
        $persisted = [];

        // Mock EntityManager
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(SystemPreferences::class)->willReturn($repo);
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });
        $em->expects($this->once())->method('flush');

        $command = new PrefsSyncCommand($registry, $em);
        $tester = new CommandTester($command);
        $tester->execute([]);

        // Status code
        $this->assertSame(0, $tester->getStatusCode());

        // Output assertions
        $out = $tester->getDisplay();
        // Assert at least some known keys are created
        $this->assertStringContainsString('Created: theme.login_image_path', $out);
        $this->assertStringContainsString('Created: maintenance.enabled', $out);

        // Ensure persisted entities are SystemPreferences
        $this->assertNotEmpty($persisted);
        foreach ($persisted as $p) {
            $this->assertInstanceOf(SystemPreferences::class, $p);
        }
    }
}
