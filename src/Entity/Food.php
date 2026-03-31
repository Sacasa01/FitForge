<?php

namespace App\Entity;

use App\Repository\FoodRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FoodRepository::class)]
#[ORM\Table(name: 'foods')]
class Food
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2)]
    private ?string $kcalPer100g = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2, options: ['default' => '0'])]
    private string $proteinG = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2, options: ['default' => '0'])]
    private string $carbsG = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2, options: ['default' => '0'])]
    private string $fatG = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2, options: ['default' => '0'])]
    private string $fiberG = '0';

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

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $this->brand = $brand;
        return $this;
    }

    public function getKcalPer100g(): ?string
    {
        return $this->kcalPer100g;
    }

    public function setKcalPer100g(string $kcalPer100g): static
    {
        $this->kcalPer100g = $kcalPer100g;
        return $this;
    }

    public function getProteinG(): string
    {
        return $this->proteinG;
    }

    public function setProteinG(string $proteinG): static
    {
        $this->proteinG = $proteinG;
        return $this;
    }

    public function getCarbsG(): string
    {
        return $this->carbsG;
    }

    public function setCarbsG(string $carbsG): static
    {
        $this->carbsG = $carbsG;
        return $this;
    }

    public function getFatG(): string
    {
        return $this->fatG;
    }

    public function setFatG(string $fatG): static
    {
        $this->fatG = $fatG;
        return $this;
    }

    public function getFiberG(): string
    {
        return $this->fiberG;
    }

    public function setFiberG(string $fiberG): static
    {
        $this->fiberG = $fiberG;
        return $this;
    }
}
