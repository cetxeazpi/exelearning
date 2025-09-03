<?php

namespace App\Entity\net\exelearning\Entity;

use App\Enum\Role;
use App\Repository\net\exelearning\Repository\OdeUsersRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'ode_users')]
#[ORM\Index(name: 'ode_users_index1', columns: ['ode_id'])]
#[ORM\Index(name: 'ode_users_index2', columns: ['user'])]
#[ORM\Entity(repositoryClass: OdeUsersRepository::class)]
class OdeUsers extends BaseEntity
{
    #[ORM\Column(name: 'ode_id', type: 'string', length: 32, nullable: false, options: ['fixed' => true])]
    protected string $odeId;

    #[ORM\Column(name: 'user', type: 'string', length: 128, nullable: false)]
    protected string $user;

    #[ORM\Column(name: 'last_action', type: 'datetime', nullable: false)]
    protected \DateTime $lastAction;
    #[ORM\Column(type: "string", enumType: Role::class)]
    private Role $role;
    #[ORM\Column(name: 'node_ip', type: 'string', length: 50, nullable: false)]
    protected string $nodeIp;

    public function getOdeId(): ?string
    {
        return $this->odeId;
    }

    public function setOdeId(string $odeId): self
    {
        $this->odeId = $odeId;

        return $this;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function setUser(string $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getLastAction(): ?\DateTimeInterface
    {
        return $this->lastAction;
    }


    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function setLastAction(\DateTimeInterface $lastAction): self
    {
        $this->lastAction = $lastAction;

        return $this;
    }

    public function getNodeIp(): ?string
    {
        return $this->nodeIp;
    }

    public function setNodeIp(string $nodeIp): self
    {
        $this->nodeIp = $nodeIp;

        return $this;
    }
}
