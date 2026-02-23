<?php

namespace App\Entity;

use App\Repository\LaunchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LaunchRepository::class)]
#[ORM\Table(name: '`launch`')]
class Launch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_launch')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'launches')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_users', nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'launches')]
    #[ORM\JoinColumn(name: 'exercise_id', referencedColumnName: 'id_exercise', nullable: false)]
    private ?BreathingExercise $breathingExercise = null;

    #[ORM\Column(name: 'launch_date', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $launchDate = null;

    #[ORM\Column(name: 'cycle_count', type: Types::SMALLINT)]
    private ?int $cycleCount = null;

    #[ORM\Column(name: 'total_duration', type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $totalDuration = null;

    public function __construct()
    {
        $this->launchDate = new \DateTimeImmutable();
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

    public function getBreathingExercise(): ?BreathingExercise
    {
        return $this->breathingExercise;
    }

    public function setBreathingExercise(?BreathingExercise $breathingExercise): static
    {
        $this->breathingExercise = $breathingExercise;

        return $this;
    }

    public function getLaunchDate(): ?\DateTimeImmutable
    {
        return $this->launchDate;
    }

    public function setLaunchDate(\DateTimeImmutable $launchDate): static
    {
        $this->launchDate = $launchDate;

        return $this;
    }

    public function getCycleCount(): ?int
    {
        return $this->cycleCount;
    }

    public function setCycleCount(int $cycleCount): static
    {
        $this->cycleCount = $cycleCount;

        return $this;
    }

    public function getTotalDuration(): ?\DateTimeImmutable
    {
        return $this->totalDuration;
    }

    public function setTotalDuration(\DateTimeImmutable $totalDuration): static
    {
        $this->totalDuration = $totalDuration;

        return $this;
    }
}
