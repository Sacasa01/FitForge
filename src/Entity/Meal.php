<?php

namespace App\Entity;

use App\Repository\MealRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MealRepository::class)]
#[ORM\Table(name: 'meals')]
class Meal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Diet::class, inversedBy: 'meals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Diet $diet = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $mealTime = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $dayOfWeek = null;

    /** @var Collection<int, MealFood> */
    #[ORM\OneToMany(targetEntity: MealFood::class, mappedBy: 'meal', cascade: ['persist', 'remove'])]
    private Collection $mealFoods;

    public function __construct()
    {
        $this->mealFoods = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDiet(): ?Diet
    {
        return $this->diet;
    }

    public function setDiet(?Diet $diet): static
    {
        $this->diet = $diet;
        return $this;
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

    public function getMealTime(): ?\DateTimeInterface
    {
        return $this->mealTime;
    }

    public function setMealTime(?\DateTimeInterface $mealTime): static
    {
        $this->mealTime = $mealTime;
        return $this;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    /** @return Collection<int, MealFood> */
    public function getMealFoods(): Collection
    {
        return $this->mealFoods;
    }

    public function addMealFood(MealFood $mealFood): static
    {
        if (!$this->mealFoods->contains($mealFood)) {
            $this->mealFoods->add($mealFood);
            $mealFood->setMeal($this);
        }
        return $this;
    }

    public function removeMealFood(MealFood $mealFood): static
    {
        if ($this->mealFoods->removeElement($mealFood)) {
            if ($mealFood->getMeal() === $this) {
                $mealFood->setMeal(null);
            }
        }
        return $this;
    }
}
