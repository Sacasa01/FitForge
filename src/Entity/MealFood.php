<?php

namespace App\Entity;

use App\Repository\MealFoodRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MealFoodRepository::class)]
#[ORM\Table(name: 'meal_foods')]
class MealFood
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Meal::class, inversedBy: 'mealFoods')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Meal $meal = null;

    #[ORM\ManyToOne(targetEntity: Food::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Food $food = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2)]
    private ?string $quantityG = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMeal(): ?Meal
    {
        return $this->meal;
    }

    public function setMeal(?Meal $meal): static
    {
        $this->meal = $meal;
        return $this;
    }

    public function getFood(): ?Food
    {
        return $this->food;
    }

    public function setFood(?Food $food): static
    {
        $this->food = $food;
        return $this;
    }

    public function getQuantityG(): ?string
    {
        return $this->quantityG;
    }

    public function setQuantityG(string $quantityG): static
    {
        $this->quantityG = $quantityG;
        return $this;
    }
}
