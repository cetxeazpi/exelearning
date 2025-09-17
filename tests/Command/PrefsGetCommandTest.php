<?php

namespace App\Tests\Command;

use App\Command\PrefsGetCommand;
use App\Service\net\exelearning\Service\SystemPreferencesService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PrefsGetCommandTest extends TestCase
{
    public function testGetReturnsValue(): void
    {
        $svc = $this->createMock(SystemPreferencesService::class);
        $svc->expects($this->once())->method('get')->with('maintenance.enabled')->willReturn(true);

        $cmd = new PrefsGetCommand($svc);
        $tester = new CommandTester($cmd);
        $tester->execute(['key' => 'maintenance.enabled']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame("true\n", $tester->getDisplay());
    }
}

