<?php

namespace App\Entity;

use App\Enum\GeneralFeeling;
use App\Repository\DietLogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DietLogRepository::class)]
#[ORM\Table(name: 'diet_logs')]
class DietLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'dietLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Diet::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Diet $diet = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $loggedAt = null;

    #[ORM\Column(length: 10, nullable: true, enumType: GeneralFeeling::class)]
    private ?GeneralFeeling $generalFeeling = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, MealLog> */
    #[ORM\OneToMany(targetEntity: MealLog::class, mappedBy: 'dietLog', cascade: ['persist', 'remove'])]
    private Collection $mealLogs;

    public function __construct()
    {
        $this->loggedAt = new \DateTime();
        $this->mealLogs = new ArrayCollection();
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

    public function getDiet(): ?Diet
    {
        return $this->diet;
    }

    public function setDiet(?Diet $diet): static
    {
        $this->diet = $diet;
        return $this;
    }

    public function getLoggedAt(): ?\DateTimeInterface
    {
        return $this->loggedAt;
    }

    public function setLoggedAt(\DateTimeInterface $loggedAt): static
    {
        $this->loggedAt = $loggedAt;
        return $this;
    }

    public function getGeneralFeeling(): ?GeneralFeeling
    {
        return $this->generalFeeling;
    }

    public function setGeneralFeeling(?GeneralFeeling $generalFeeling): static
    {
        $this->generalFeeling = $generalFeeling;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    /** @return Collection<int, MealLog> */
    public function getMealLogs(): Collection
    {
        return $this->mealLogs;
    }

    public function addMealLog(MealLog $mealLog): static
    {
        if (!$this->mealLogs->contains($mealLog)) {
            $this->mealLogs->add($mealLog);
            $mealLog->setDietLog($this);
        }
        return $this;
    }

    public function removeMealLog(MealLog $mealLog): static
    {
        if ($this->mealLogs->removeElement($mealLog)) {
            if ($mealLog->getDietLog() === $this) {
                $mealLog->setDietLog(null);
            }
        }
        return $this;
    }
}
