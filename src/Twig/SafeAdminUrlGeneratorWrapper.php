<?php

namespace App\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator as BaseAdminUrlGenerator;

/**
 * Wrapper around EasyAdmin's AdminUrlGenerator to ignore null controllers.
 *
 * It preserves method chaining by returning $this whenever the inner call
 * would normally return the inner generator instance.
 */
class SafeAdminUrlGeneratorWrapper
{
    public function __construct(private BaseAdminUrlGenerator $inner)
    {
    }

    /**
     * Allow null/empty controller values without throwing.
     */
    public function setController(?string $crudControllerFqcn): self
    {
        if (null === $crudControllerFqcn || '' === $crudControllerFqcn) {
            // Optional debug hint in logs to help track the origin
            @error_log('[EasyAdmin] Ignoring null controller in ea_url()->setController().');

            return $this;
        }

        $result = $this->inner->setController($crudControllerFqcn);

        return $result === $this->inner ? $this : $result;
    }

    /**
     * Forward any other call to the inner generator while keeping chaining.
     */
    public function __call(string $name, array $arguments)
    {
        $result = $this->inner->{$name}(...$arguments);

        return $result === $this->inner ? $this : $result;
    }
}
