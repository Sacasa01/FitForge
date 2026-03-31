<?php

namespace App\Entity;

use App\Repository\SessionExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionExerciseRepository::class)]
#[ORM\Table(name: 'session_exercises')]
class SessionExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WorkoutSession::class, inversedBy: 'sessionExercises')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?WorkoutSession $session = null;

    #[ORM\ManyToOne(targetEntity: Exercise::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Exercise $exercise = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $orderIndex = 0;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $enjoyment = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $difficulty = null;

    /** @var Collection<int, ExerciseSet> */
    #[ORM\OneToMany(targetEntity: ExerciseSet::class, mappedBy: 'sessionExercise', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['setNumber' => 'ASC'])]
    private Collection $exerciseSets;

    public function __construct()
    {
        $this->exerciseSets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ?WorkoutSession
    {
        return $this->session;
    }

    public function setSession(?WorkoutSession $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function getExercise(): ?Exercise
    {
        return $this->exercise;
    }

    public function setExercise(?Exercise $exercise): static
    {
        $this->exercise = $exercise;
        return $this;
    }

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): static
    {
        $this->orderIndex = $orderIndex;
        return $this;
    }

    public function getEnjoyment(): ?int
    {
        return $this->enjoyment;
    }

    public function setEnjoyment(?int $enjoyment): static
    {
        $this->enjoyment = $enjoyment;
        return $this;
    }

    public function getDifficulty(): ?int
    {
        return $this->difficulty;
    }

    public function setDifficulty(?int $difficulty): static
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    /** @return Collection<int, ExerciseSet> */
    public function getExerciseSets(): Collection
    {
        return $this->exerciseSets;
    }

    public function addExerciseSet(ExerciseSet $exerciseSet): static
    {
        if (!$this->exerciseSets->contains($exerciseSet)) {
            $this->exerciseSets->add($exerciseSet);
            $exerciseSet->setSessionExercise($this);
        }
        return $this;
    }

    public function removeExerciseSet(ExerciseSet $exerciseSet): static
    {
        if ($this->exerciseSets->removeElement($exerciseSet)) {
            if ($exerciseSet->getSessionExercise() === $this) {
                $exerciseSet->setSessionExercise(null);
            }
        }
        return $this;
    }
}
