<?php
// src/Twig/SystemPreferencesExtension.php
namespace App\Twig;

use App\Service<SystemPreferences>;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SystemPreferencesExtension extends AbstractExtension
{
    public function __construct(private SystemPreferences $prefs) {}
    public function getFunctions(): array {
        return [
            new TwigFunction('sys_pref', fn(string $k, $d=null) => $this->prefs->get($k, $d)),
            new TwigFunction('sys_pref_bool', fn(string $k, bool $d=false) => (bool)$this->prefs->get($k, $d)),
        ];
    }
}
