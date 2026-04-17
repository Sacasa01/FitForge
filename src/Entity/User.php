<?php

namespace App\Entity;

use App\Enum\GoalType;
use App\Enum\Role;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $username = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $birthdate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $heightCm = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $currentWeightKg = null;

    #[ORM\Column(length: 20, enumType: GoalType::class)]
    private ?GoalType $goalType = null;

    #[ORM\Column(length: 10, enumType: Role::class)]
    private Role $role = Role::User;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $dailyProteinG = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $dailyCarbsG = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $dailyFatG = null;

    #[ORM\ManyToOne(targetEntity: Routine::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Routine $assignedRoutine = null;

    #[ORM\ManyToOne(targetEntity: Diet::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Diet $assignedDiet = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, UserWeightLog> */
    #[ORM\OneToMany(targetEntity: UserWeightLog::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $weightLogs;

    /** @var Collection<int, WorkoutSession> */
    #[ORM\OneToMany(targetEntity: WorkoutSession::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $workoutSessions;

    /** @var Collection<int, DietLog> */
    #[ORM\OneToMany(targetEntity: DietLog::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $dietLogs;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->weightLogs = new ArrayCollection();
        $this->workoutSessions = new ArrayCollection();
        $this->dietLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getBirthdate(): ?\DateTimeInterface
    {
        return $this->birthdate;
    }

    public function setBirthdate(?\DateTimeInterface $birthdate): static
    {
        $this->birthdate = $birthdate;
        return $this;
    }

    public function getHeightCm(): ?string
    {
        return $this->heightCm;
    }

    public function setHeightCm(?string $heightCm): static
    {
        $this->heightCm = $heightCm;
        return $this;
    }

    public function getCurrentWeightKg(): ?string
    {
        return $this->currentWeightKg;
    }

    public function setCurrentWeightKg(?string $currentWeightKg): static
    {
        $this->currentWeightKg = $currentWeightKg;
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

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getAssignedRoutine(): ?Routine
    {
        return $this->assignedRoutine;
    }

    public function setAssignedRoutine(?Routine $assignedRoutine): static
    {
        $this->assignedRoutine = $assignedRoutine;
        return $this;
    }

    public function getAssignedDiet(): ?Diet
    {
        return $this->assignedDiet;
    }

    public function setAssignedDiet(?Diet $assignedDiet): static
    {
        $this->assignedDiet = $assignedDiet;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDailyProteinG(): ?string
    {
        return $this->dailyProteinG;
    }

    public function setDailyProteinG(?string $dailyProteinG): static
    {
        $this->dailyProteinG = $dailyProteinG;
        return $this;
    }

    public function getDailyCarbsG(): ?string
    {
        return $this->dailyCarbsG;
    }

    public function setDailyCarbsG(?string $dailyCarbsG): static
    {
        $this->dailyCarbsG = $dailyCarbsG;
        return $this;
    }

    public function getDailyFatG(): ?string
    {
        return $this->dailyFatG;
    }

    public function setDailyFatG(?string $dailyFatG): static
    {
        $this->dailyFatG = $dailyFatG;
        return $this;
    }

    /** @return Collection<int, UserWeightLog> */
    public function getWeightLogs(): Collection
    {
        return $this->weightLogs;
    }

    /** @return Collection<int, WorkoutSession> */
    public function getWorkoutSessions(): Collection
    {
        return $this->workoutSessions;
    }

    /** @return Collection<int, DietLog> */
    public function getDietLogs(): Collection
    {
        return $this->dietLogs;
    }

    // UserInterface methods

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return ['ROLE_' . strtoupper($this->role->value)];
    }

    public function eraseCredentials(): void
    {
    }
}
