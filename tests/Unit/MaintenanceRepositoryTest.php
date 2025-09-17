<?php

namespace App\Tests\Unit;

use App\Service\net\exelearning\Service\SystemPreferencesService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MaintenanceRepositoryTest extends KernelTestCase
{
    public function test_read_write_toggle(): void
    {
        self::bootKernel();
        $prefs = static::getContainer()->get(SystemPreferencesService::class);

        // Write
        $prefs->set('maintenance.enabled', true, 'bool', 'tests');
        $prefs->set('maintenance.message', 'Testing', 'string', 'tests');

        // Read back via service
        self::assertTrue((bool) $prefs->get('maintenance.enabled'));
        self::assertSame('Testing', $prefs->get('maintenance.message'));
    }

    protected function tearDown(): void
    {
        if (static::getContainer()) {
            try {
                $prefs = static::getContainer()->get(\App\Service\net\exelearning\Service\SystemPreferencesService::class);
                $prefs->set('maintenance.enabled', false, 'bool', 'tests');
                $prefs->set('maintenance.message', null, 'string', 'tests');
            } catch (\Throwable) {
                // ignore
            }
        }
        parent::tearDown();
    }
}
