<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: '`role`')]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_role')]
    private ?int $id = null;

    #[ORM\Column(name: 'role_code', length: 50, unique: true)]
    private ?string $roleCode = null;

    #[ORM\Column(name: 'role_label', length: 100)]
    private ?string $roleLabel = null;

    #[ORM\OneToMany(mappedBy: 'role', targetEntity: RoleAssignment::class, orphanRemoval: true)]
    private Collection $roleAssignments;

    public function __construct()
    {
        $this->roleAssignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoleCode(): ?string
    {
        return $this->roleCode;
    }

    public function setRoleCode(string $roleCode): static
    {
        $this->roleCode = $roleCode;

        return $this;
    }

    public function getRoleLabel(): ?string
    {
        return $this->roleLabel;
    }

    public function setRoleLabel(string $roleLabel): static
    {
        $this->roleLabel = $roleLabel;

        return $this;
    }

    /**
     * @return Collection<int, RoleAssignment>
     */
    public function getRoleAssignments(): Collection
    {
        return $this->roleAssignments;
    }

    public function addRoleAssignment(RoleAssignment $roleAssignment): static
    {
        if (!$this->roleAssignments->contains($roleAssignment)) {
            $this->roleAssignments->add($roleAssignment);
            $roleAssignment->setRole($this);
        }

        return $this;
    }

    public function removeRoleAssignment(RoleAssignment $roleAssignment): static
    {
        if ($this->roleAssignments->removeElement($roleAssignment)) {
            if ($roleAssignment->getRole() === $this) {
                $roleAssignment->setRole(null);
            }
        }

        return $this;
    }
}
