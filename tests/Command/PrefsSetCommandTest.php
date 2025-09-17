<?php

namespace App\Tests\Command;

use App\Command\PrefsSetCommand;
use App\Service\net\exelearning\Service\SystemPreferencesService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PrefsSetCommandTest extends TestCase
{
    public function testSetBoolWithType(): void
    {
        $svc = $this->createMock(SystemPreferencesService::class);
        $svc->expects($this->once())
            ->method('set')
            ->with('maintenance.enabled', true, 'bool', 'cli');

        $cmd = new PrefsSetCommand($svc);
        $tester = new CommandTester($cmd);
        $tester->execute([
            'key' => 'maintenance.enabled',
            'value' => 'true',
            '--type' => 'bool',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Updated maintenance.enabled', $tester->getDisplay());
    }
}

