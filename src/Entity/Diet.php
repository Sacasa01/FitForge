<?php

namespace App\Entity;

use App\Enum\GoalType;
use App\Repository\DietRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DietRepository::class)]
#[ORM\Table(name: 'diets')]
class Diet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $dailyKcal = null;

    #[ORM\Column(length: 20, enumType: GoalType::class)]
    private ?GoalType $goalType = null;

    /** @var Collection<int, Meal> */
    #[ORM\OneToMany(targetEntity: Meal::class, mappedBy: 'diet', cascade: ['persist', 'remove'])]
    private Collection $meals;

    public function __construct()
    {
        $this->meals = new ArrayCollection();
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

    public function getDailyKcal(): ?int
    {
        return $this->dailyKcal;
    }

    public function setDailyKcal(?int $dailyKcal): static
    {
        $this->dailyKcal = $dailyKcal;
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

    /** @return Collection<int, Meal> */
    public function getMeals(): Collection
    {
        return $this->meals;
    }

    public function addMeal(Meal $meal): static
    {
        if (!$this->meals->contains($meal)) {
            $this->meals->add($meal);
            $meal->setDiet($this);
        }
        return $this;
    }

    public function removeMeal(Meal $meal): static
    {
        if ($this->meals->removeElement($meal)) {
            if ($meal->getDiet() === $this) {
                $meal->setDiet(null);
            }
        }
        return $this;
    }
}
