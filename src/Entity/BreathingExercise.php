<?php

namespace App\Entity;

use App\Repository\BreathingExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BreathingExerciseRepository::class)]
#[ORM\Table(name: 'breathing_exercise')]
class BreathingExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_exercise')]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column(name: 'inhale_duration', type: 'smallint')]
    private ?int $inhaleDuration = null;

    #[ORM\Column(name: 'hold_duration', type: 'smallint')]
    private ?int $holdDuration = null;

    #[ORM\Column(name: 'exhale_duration', type: 'smallint')]
    private ?int $exhaleDuration = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\OneToMany(mappedBy: 'breathingExercise', targetEntity: Launch::class, orphanRemoval: true)]
    private Collection $launches;

    public function __construct()
    {
        $this->launches = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getInhaleDuration(): ?int
    {
        return $this->inhaleDuration;
    }

    public function setInhaleDuration(int $inhaleDuration): static
    {
        $this->inhaleDuration = $inhaleDuration;

        return $this;
    }

    public function getHoldDuration(): ?int
    {
        return $this->holdDuration;
    }

    public function setHoldDuration(int $holdDuration): static
    {
        $this->holdDuration = $holdDuration;

        return $this;
    }

    public function getExhaleDuration(): ?int
    {
        return $this->exhaleDuration;
    }

    public function setExhaleDuration(int $exhaleDuration): static
    {
        $this->exhaleDuration = $exhaleDuration;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection<int, Launch>
     */
    public function getLaunches(): Collection
    {
        return $this->launches;
    }

    public function addLaunch(Launch $launch): static
    {
        if (!$this->launches->contains($launch)) {
            $this->launches->add($launch);
            $launch->setBreathingExercise($this);
        }

        return $this;
    }

    public function removeLaunch(Launch $launch): static
    {
        if ($this->launches->removeElement($launch)) {
            if ($launch->getBreathingExercise() === $this) {
                $launch->setBreathingExercise(null);
            }
        }

        return $this;
    }
}
