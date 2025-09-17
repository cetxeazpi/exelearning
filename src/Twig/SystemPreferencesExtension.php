<?php

// src/Twig/SystemPreferencesExtension.php

namespace App\Twig;

use App\Service\net\exelearning\Service\SystemPreferencesService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SystemPreferencesExtension extends AbstractExtension
{
    public function __construct(private SystemPreferencesService $prefs)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sys_pref', fn (string $k, $d = null) => $this->prefs->get($k, $d)),
            new TwigFunction('sys_pref_bool', fn (string $k, bool $d = false) => (bool) $this->prefs->get($k, $d)),
        ];
    }
}
