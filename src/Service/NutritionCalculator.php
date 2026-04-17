<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\ActivityLevel;
use App\Enum\GoalType;

/**
 * Computes BMI, Basal Metabolic Rate, TDEE and recommended daily macros
 * based on a user's age, body composition, height, weight and goal.
 */
class NutritionCalculator
{
    private const KCAL_PER_G_PROTEIN = 4.0;
    private const KCAL_PER_G_CARBS = 4.0;
    private const KCAL_PER_G_FAT = 9.0;

    /**
     * Body Mass Index (kg/m²).
     */
    public function calculateBmi(float $heightCm, float $weightKg): float
    {
        if ($heightCm <= 0) {
            throw new \InvalidArgumentException('heightCm must be positive');
        }
        $heightM = $heightCm / 100.0;
        return $weightKg / ($heightM * $heightM);
    }

    /**
     * WHO classification for a BMI value.
     */
    public function classifyBmi(float $bmi): string
    {
        return match (true) {
            $bmi < 18.5 => 'underweight',
            $bmi < 25.0 => 'normal',
            $bmi < 30.0 => 'overweight',
            default => 'obese',
        };
    }

    /**
     * Katch-McArdle BMR — preferred when body-fat % is known since it
     * is sex-independent and keyed to lean body mass.
     */
    public function calculateBmrKatchMcArdle(float $weightKg, float $bodyFatPercent): float
    {
        if ($bodyFatPercent < 2.0 || $bodyFatPercent > 60.0) {
            throw new \InvalidArgumentException('bodyFatPercent must be between 2 and 60');
        }
        $leanMass = $weightKg * (1.0 - $bodyFatPercent / 100.0);
        return 370.0 + 21.6 * $leanMass;
    }

    /**
     * Mifflin-St Jeor BMR — fallback when body-fat % is not available.
     * $sex is 'male' or 'female'.
     */
    public function calculateBmrMifflin(float $weightKg, float $heightCm, int $age, string $sex): float
    {
        $base = 10.0 * $weightKg + 6.25 * $heightCm - 5.0 * $age;
        return $sex === 'female' ? $base - 161.0 : $base + 5.0;
    }

    public function calculateTdee(float $bmr, ActivityLevel $activityLevel): float
    {
        return $bmr * $activityLevel->multiplier();
    }

    /**
     * Recommend daily macros for a TDEE and goal. Protein is pinned by lean-mass
     * intake per kg; fat is a percentage of adjusted calories; carbs fill the rest.
     *
     * @return array{kcal:float, proteinG:float, carbsG:float, fatG:float, kcalAdjusted:float, deficitOrSurplus:float}
     */
    public function recommendMacros(float $tdee, float $weightKg, GoalType $goal): array
    {
        [$kcalFactor, $proteinPerKg, $fatKcalRatio] = match ($goal) {
            GoalType::WeightLoss => [0.80, 2.2, 0.25],
            GoalType::MuscleGain => [1.10, 2.0, 0.25],
            GoalType::Maintenance => [1.00, 1.6, 0.30],
            GoalType::Endurance => [1.05, 1.6, 0.25],
        };

        $kcalAdjusted = $tdee * $kcalFactor;
        $proteinG = $weightKg * $proteinPerKg;
        $fatG = ($kcalAdjusted * $fatKcalRatio) / self::KCAL_PER_G_FAT;

        $remainingKcal = $kcalAdjusted
            - $proteinG * self::KCAL_PER_G_PROTEIN
            - $fatG * self::KCAL_PER_G_FAT;
        $carbsG = max(0.0, $remainingKcal / self::KCAL_PER_G_CARBS);

        return [
            'kcal' => round($tdee, 0),
            'kcalAdjusted' => round($kcalAdjusted, 0),
            'deficitOrSurplus' => round($kcalAdjusted - $tdee, 0),
            'proteinG' => round($proteinG, 1),
            'carbsG' => round($carbsG, 1),
            'fatG' => round($fatG, 1),
        ];
    }

    /**
     * Full nutrition profile for a user. Requires birthdate, heightCm, weightKg
     * and goalType on the user; $bodyFatPercent is optional (if null and $sex is
     * provided, falls back to Mifflin-St Jeor).
     *
     * @return array{
     *     age:int,
     *     heightCm:float,
     *     weightKg:float,
     *     bodyFatPercent:?float,
     *     bmi:float,
     *     bmiCategory:string,
     *     bmr:float,
     *     bmrFormula:string,
     *     activityLevel:string,
     *     tdee:float,
     *     goal:string,
     *     macros:array{kcal:float, kcalAdjusted:float, deficitOrSurplus:float, proteinG:float, carbsG:float, fatG:float},
     * }
     */
    public function profileFor(
        User $user,
        ?float $bodyFatPercent = null,
        ?ActivityLevel $activityLevelOverride = null,
        ?string $sex = null,
    ): array {
        $birthdate = $user->getBirthdate();
        $heightCm = $user->getHeightCm() !== null ? (float) $user->getHeightCm() : null;
        $weightKg = $user->getCurrentWeightKg() !== null ? (float) $user->getCurrentWeightKg() : null;
        $goal = $user->getGoalType();
        $activityLevel = $activityLevelOverride ?? $user->getActivityLevel();

        if ($birthdate === null || $heightCm === null || $weightKg === null || $goal === null) {
            throw new \DomainException(
                'User profile is incomplete: birthdate, heightCm, currentWeightKg and goalType are required',
            );
        }
        if ($activityLevel === null) {
            throw new \DomainException(
                'Activity level is required: set user.activityLevel or pass an override',
            );
        }

        $age = $this->ageFromBirthdate($birthdate);

        if ($bodyFatPercent !== null) {
            $bmr = $this->calculateBmrKatchMcArdle($weightKg, $bodyFatPercent);
            $bmrFormula = 'katch_mcardle';
        } elseif ($sex !== null) {
            $bmr = $this->calculateBmrMifflin($weightKg, $heightCm, $age, $sex);
            $bmrFormula = 'mifflin_st_jeor';
        } else {
            throw new \InvalidArgumentException(
                'Either bodyFatPercent or sex must be provided to compute BMR',
            );
        }

        $tdee = $this->calculateTdee($bmr, $activityLevel);
        $bmi = $this->calculateBmi($heightCm, $weightKg);

        return [
            'age' => $age,
            'heightCm' => $heightCm,
            'weightKg' => $weightKg,
            'bodyFatPercent' => $bodyFatPercent,
            'bmi' => round($bmi, 2),
            'bmiCategory' => $this->classifyBmi($bmi),
            'bmr' => round($bmr, 0),
            'bmrFormula' => $bmrFormula,
            'activityLevel' => $activityLevel->value,
            'tdee' => round($tdee, 0),
            'goal' => $goal->value,
            'macros' => $this->recommendMacros($tdee, $weightKg, $goal),
        ];
    }

    public function ageFromBirthdate(\DateTimeInterface $birthdate, ?\DateTimeInterface $asOf = null): int
    {
        $asOf ??= new \DateTimeImmutable();
        return $birthdate->diff($asOf)->y;
    }
}
