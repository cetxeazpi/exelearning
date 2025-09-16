<?php

namespace App\Twig;

use App\Service\ThemeSettingsProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ThemeSettingsExtension extends AbstractExtension
{
    public function __construct(private readonly ThemeSettingsProvider $provider)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_settings', [$this, 'themeSettings']),
        ];
    }

    public function themeSettings(): array
    {
        $s = $this->provider->get();

        return [
            'loginImage' => $s?->getLoginImagePath(),
            'loginLogo' => $s?->getLoginLogoPath(),
            'favicon' => $s?->getFaviconPath(),
        ];
    }
}
