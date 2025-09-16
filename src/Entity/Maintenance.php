<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'maintenance')]
#[ORM\HasLifecycleCallbacks]
class Maintenance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $enabled = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $scheduledEndAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $updatedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getScheduledEndAt(): ?\DateTimeImmutable
    {
        return $this->scheduledEndAt;
    }

    public function setScheduledEndAt(?\DateTimeImmutable $scheduledEndAt): self
    {
        $this->scheduledEndAt = $scheduledEndAt;

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
