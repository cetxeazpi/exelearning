<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'theme_settings')]
class ThemeSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $loginImagePath = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $loginLogoPath = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $faviconPath = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $updatedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoginImagePath(): ?string
    {
        return $this->loginImagePath;
    }

    public function setLoginImagePath(?string $p): self
    {
        $this->loginImagePath = $p;

        return $this;
    }

    public function getLoginLogoPath(): ?string
    {
        return $this->loginLogoPath;
    }

    public function setLoginLogoPath(?string $p): self
    {
        $this->loginLogoPath = $p;

        return $this;
    }

    public function getFaviconPath(): ?string
    {
        return $this->faviconPath;
    }

    public function setFaviconPath(?string $p): self
    {
        $this->faviconPath = $p;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $d): self
    {
        $this->updatedAt = $d;

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $u): self
    {
        $this->updatedBy = $u;

        return $this;
    }
}
