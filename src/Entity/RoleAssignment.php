<?php

namespace App\Entity;

use App\Repository\RoleAssignmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleAssignmentRepository::class)]
#[ORM\Table(name: 'role_assignment')]
class RoleAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_role_assignment')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'roleAssignments')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_users', nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'roleAssignments')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id_role', nullable: false)]
    private ?Role $role = null;

    #[ORM\Column(name: 'assignment_date', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $assignmentDate = null;

    public function __construct()
    {
        $this->assignmentDate = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getAssignmentDate(): ?\DateTimeImmutable
    {
        return $this->assignmentDate;
    }

    public function setAssignmentDate(\DateTimeImmutable $assignmentDate): static
    {
        $this->assignmentDate = $assignmentDate;

        return $this;
    }
}
