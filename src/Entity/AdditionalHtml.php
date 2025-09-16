<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'additional_html')]
#[ORM\HasLifecycleCallbacks]
class AdditionalHtml
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $headHtml = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $topOfBodyHtml = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $footerHtml = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $updatedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHeadHtml(): ?string
    {
        return $this->headHtml;
    }

    public function setHeadHtml(?string $headHtml): self
    {
        $this->headHtml = $headHtml;

        return $this;
    }

    public function getTopOfBodyHtml(): ?string
    {
        return $this->topOfBodyHtml;
    }

    public function setTopOfBodyHtml(?string $topOfBodyHtml): self
    {
        $this->topOfBodyHtml = $topOfBodyHtml;

        return $this;
    }

    public function getFooterHtml(): ?string
    {
        return $this->footerHtml;
    }

    public function setFooterHtml(?string $footerHtml): self
    {
        $this->footerHtml = $footerHtml;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touchTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
