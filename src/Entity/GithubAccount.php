<?php

namespace App\Entity;

use App\Entity\net\exelearning\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'github_accounts')]
class GithubAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 50)]
    private string $provider = 'github';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $accessTokenEnc = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $refreshTokenEnc = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\Column(type: 'string', length: 190, nullable: true)]
    private ?string $githubLogin = null;

    #[ORM\Column(type: 'string', length: 190, nullable: true)]
    private ?string $githubId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getAccessTokenEnc(): ?string
    {
        return $this->accessTokenEnc;
    }

    public function setAccessTokenEnc(?string $v): self
    {
        $this->accessTokenEnc = $v;

        return $this;
    }

    public function getRefreshTokenEnc(): ?string
    {
        return $this->refreshTokenEnc;
    }

    public function setRefreshTokenEnc(?string $v): self
    {
        $this->refreshTokenEnc = $v;

        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $d): self
    {
        $this->tokenExpiresAt = $d;

        return $this;
    }

    public function getGithubLogin(): ?string
    {
        return $this->githubLogin;
    }

    public function setGithubLogin(?string $v): self
    {
        $this->githubLogin = $v;

        return $this;
    }

    public function getGithubId(): ?string
    {
        return $this->githubId;
    }

    public function setGithubId(?string $v): self
    {
        $this->githubId = $v;

        return $this;
    }
}
