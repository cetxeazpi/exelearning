<?php

namespace App\Tests\Unit;

use App\Entity\Maintenance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MaintenanceRepositoryTest extends KernelTestCase
{
    public function test_read_write_toggle(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var Maintenance|null $maintenance */
        $maintenance = $em->getRepository(Maintenance::class)->findOneBy([]);
        if (!$maintenance instanceof Maintenance) {
            $maintenance = new Maintenance();
            $em->persist($maintenance);
        }

        $maintenance->setEnabled(true)->setMessage('Testing');
        $em->flush();

        $found = $em->getRepository(Maintenance::class)->findOneBy([]);
        self::assertInstanceOf(Maintenance::class, $found);
        self::assertTrue($found->isEnabled());
        self::assertSame('Testing', $found->getMessage());
    }
}

