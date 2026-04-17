<?php

namespace App\Entity;

use App\Repository\UserMealFoodOverrideRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserMealFoodOverrideRepository::class)]
#[ORM\Table(name: 'user_meal_food_overrides')]
#[ORM\UniqueConstraint(name: 'uniq_user_meal_food', columns: ['user_id', 'meal_food_id'])]
class UserMealFoodOverride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: MealFood::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MealFood $mealFood = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2)]
    private ?string $quantityG = null;

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

    public function getMealFood(): ?MealFood
    {
        return $this->mealFood;
    }

    public function setMealFood(?MealFood $mealFood): static
    {
        $this->mealFood = $mealFood;
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
