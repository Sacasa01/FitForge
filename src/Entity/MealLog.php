<?php

namespace App\Entity;

use App\Repository\MealLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MealLogRepository::class)]
#[ORM\Table(name: 'meal_logs')]
class MealLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DietLog::class, inversedBy: 'mealLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DietLog $dietLog = null;

    #[ORM\ManyToOne(targetEntity: Meal::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Meal $meal = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $enjoyment = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $satiety = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDietLog(): ?DietLog
    {
        return $this->dietLog;
    }

    public function setDietLog(?DietLog $dietLog): static
    {
        $this->dietLog = $dietLog;
        return $this;
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

    public function getEnjoyment(): ?int
    {
        return $this->enjoyment;
    }

    public function setEnjoyment(?int $enjoyment): static
    {
        $this->enjoyment = $enjoyment;
        return $this;
    }

    public function getSatiety(): ?int
    {
        return $this->satiety;
    }

    public function setSatiety(?int $satiety): static
    {
        $this->satiety = $satiety;
        return $this;
    }
}
