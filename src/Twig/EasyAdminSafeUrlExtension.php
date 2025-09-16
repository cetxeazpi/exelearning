<?php

namespace App\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Overrides the ea_url() Twig function to return a wrapper that
 * tolerates null in setController().
 */
class EasyAdminSafeUrlExtension extends AbstractExtension
{
    public function __construct(private AdminUrlGenerator $urlGenerator)
    {
    }

    public function getFunctions(): array
    {
        return [
            // Define with same name as EasyAdmin's function; Twig uses the latest
            // registered function when names collide.
            new TwigFunction('ea_url', [$this, 'eaUrl']),
        ];
    }

    public function eaUrl(): SafeAdminUrlGeneratorWrapper
    {
        // Clone to avoid side effects, mirroring EA behavior
        $cloned = clone $this->urlGenerator;

        return new SafeAdminUrlGeneratorWrapper($cloned);
    }
}
