<?php

namespace App\Tests\Command;

use App\Command\PrefsListCommand;
use App\Config\SystemPrefRegistry;
use App\Service\net\exelearning\Service\SystemPreferencesService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PrefsListCommandTest extends TestCase
{
    public function testListWithPrefixFiltersAndPrintsValues(): void
    {
        $registry = new SystemPrefRegistry();

        $svc = $this->createMock(SystemPreferencesService::class);
        $svc->method('get')->willReturnCallback(function (string $key, $default = null) {
            return match ($key) {
                'maintenance.enabled' => true,
                'maintenance.message' => 'Planned',
                default => $default,
            };
        });

        $cmd = new PrefsListCommand($registry, $svc);
        $tester = new CommandTester($cmd);
        $tester->execute(['--prefix' => 'maintenance.']);

        $out = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('maintenance.enabled [bool]: true', $out);
        $this->assertStringContainsString("maintenance.message [string]: 'Planned'", $out);
        $this->assertStringNotContainsString('theme.login_image_path', $out);
    }
}
