<?php

namespace App\Service;

use App\Entity\ThemeSettings;
use Doctrine\ORM\EntityManagerInterface;

class ThemeSettingsProvider
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(): ?ThemeSettings
    {
        try {
            /** @var ThemeSettings|null $s */
            $s = $this->em->getRepository(ThemeSettings::class)->findOneBy([]);

            return $s;
        } catch (\Throwable $e) {
            // Be resilient if schema is not yet created in certain environments/tests
            return null;
        }
    }
}
