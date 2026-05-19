<?php

namespace App\Entity;

use App\Enum\DayOfWeek;
use App\Repository\UserRoutineScheduleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRoutineScheduleRepository::class)]
#[ORM\Table(name: 'user_routine_schedule')]
#[ORM\UniqueConstraint(name: 'user_day_unique', columns: ['user_id', 'day_of_week'])]
class UserRoutineSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'routineSchedules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 10, enumType: DayOfWeek::class)]
    private DayOfWeek $dayOfWeek;

    #[ORM\ManyToOne(targetEntity: Routine::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Routine $routine = null;

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

    public function getDayOfWeek(): DayOfWeek
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(DayOfWeek $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getRoutine(): ?Routine
    {
        return $this->routine;
    }

    public function setRoutine(?Routine $routine): static
    {
        $this->routine = $routine;
        return $this;
    }
}
