<?php

namespace App\Entity;

use App\Enum\Difficulty;
use App\Enum\GoalType;
use App\Repository\RoutineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoutineRepository::class)]
#[ORM\Table(name: 'routines')]
class Routine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: Difficulty::class)]
    private Difficulty $difficulty = Difficulty::Beginner;

    #[ORM\Column(length: 20, enumType: GoalType::class)]
    private ?GoalType $goalType = null;

    /** @var Collection<int, RoutineExercise> */
    #[ORM\OneToMany(targetEntity: RoutineExercise::class, mappedBy: 'routine', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    private Collection $routineExercises;

    public function __construct()
    {
        $this->routineExercises = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDifficulty(): Difficulty
    {
        return $this->difficulty;
    }

    public function setDifficulty(Difficulty $difficulty): static
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function getGoalType(): ?GoalType
    {
        return $this->goalType;
    }

    public function setGoalType(GoalType $goalType): static
    {
        $this->goalType = $goalType;
        return $this;
    }

    /** @return Collection<int, RoutineExercise> */
    public function getRoutineExercises(): Collection
    {
        return $this->routineExercises;
    }

    public function addRoutineExercise(RoutineExercise $routineExercise): static
    {
        if (!$this->routineExercises->contains($routineExercise)) {
            $this->routineExercises->add($routineExercise);
            $routineExercise->setRoutine($this);
        }
        return $this;
    }

    public function removeRoutineExercise(RoutineExercise $routineExercise): static
    {
        if ($this->routineExercises->removeElement($routineExercise)) {
            if ($routineExercise->getRoutine() === $this) {
                $routineExercise->setRoutine(null);
            }
        }
        return $this;
    }
}
