<?php

namespace App\Entity;

use App\Repository\ExerciseSetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciseSetRepository::class)]
#[ORM\Table(name: 'exercise_sets')]
class ExerciseSet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SessionExercise::class, inversedBy: 'exerciseSets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SessionExercise $sessionExercise = null;

    #[ORM\Column(type: 'smallint')]
    private ?int $setNumber = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $reps = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $weightKg = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $rpe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionExercise(): ?SessionExercise
    {
        return $this->sessionExercise;
    }

    public function setSessionExercise(?SessionExercise $sessionExercise): static
    {
        $this->sessionExercise = $sessionExercise;
        return $this;
    }

    public function getSetNumber(): ?int
    {
        return $this->setNumber;
    }

    public function setSetNumber(int $setNumber): static
    {
        $this->setNumber = $setNumber;
        return $this;
    }

    public function getReps(): ?int
    {
        return $this->reps;
    }

    public function setReps(?int $reps): static
    {
        $this->reps = $reps;
        return $this;
    }

    public function getWeightKg(): ?string
    {
        return $this->weightKg;
    }

    public function setWeightKg(?string $weightKg): static
    {
        $this->weightKg = $weightKg;
        return $this;
    }

    public function getRpe(): ?int
    {
        return $this->rpe;
    }

    public function setRpe(?int $rpe): static
    {
        $this->rpe = $rpe;
        return $this;
    }
}
