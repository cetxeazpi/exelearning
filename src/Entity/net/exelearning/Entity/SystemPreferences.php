<?php

namespace App\Entity\net\exelearning\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'system_preferences')]
#[ORM\UniqueConstraint(name: 'uniq_pref_key', columns: ['pref_key'])]
#[ORM\HasLifecycleCallbacks]
class SystemPreferences
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'pref_key', type: 'string', length: 191, unique: true)]
    private string $key;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $updatedBy = null;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $dt): self
    {
        $this->updatedAt = $dt;

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $by): self
    {
        $this->updatedBy = $by;

        return $this;
    }

    // BOOL
    public function getValueBool(): bool
    {
        return (bool) (int) ($this->value ?? '0');
    }

    public function setValueBool(bool $v): self
    {
        $this->value = $v ? '1' : '0';

        return $this;
    }

    // INT
    public function getValueInt(): ?int
    {
        return null === $this->value ? null : (int) $this->value;
    }

    public function setValueInt(?int $v): self
    {
        $this->value = null === $v ? null : (string) $v;

        return $this;
    }

    // FLOAT
    public function getValueFloat(): ?float
    {
        return null === $this->value ? null : (float) $this->value;
    }

    public function setValueFloat(?float $v): self
    {
        $this->value = null === $v ? null : (string) $v;

        return $this;
    }

    // DATE (solo fecha)
    public function getValueDate(): ?\DateTimeInterface
    {
        if (!$this->value) {
            return null;
        }
        // admite 'Y-m-d' o ISO/‘Y-m-d H:i:s’
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', substr($this->value, 0, 10));

        return $d ?: new \DateTimeImmutable($this->value);
    }

    public function setValueDate(?\DateTimeInterface $d): self
    {
        $this->value = $d ? $d->format('Y-m-d') : null;

        return $this;
    }

    // DATETIME
    public function getValueDateTime(): ?\DateTimeInterface
    {
        return $this->value ? new \DateTimeImmutable($this->value) : null;
    }

    public function setValueDateTime(?\DateTimeInterface $d): self
    {
        $this->value = $d ? $d->format('Y-m-d H:i:s') : null;

        return $this;
    }
}
