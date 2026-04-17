<?php

namespace App\Service;

use App\Entity\Meal;

/**
 * Adjusts a meal's ingredient quantities to match per-meal macro targets
 * via gradient descent on per-ingredient scalers.
 */
class MealBalancer
{
    private const MIN_SCALER = 0.25;
    private const MAX_SCALER = 4.0;
    private const LEARNING_RATE = 0.05;
    private const MAX_ITERATIONS = 400;
    private const CONVERGENCE_EPSILON = 1e-6;

    /**
     * @param array<int, array{foodId:int, name:string, quantityG:float, proteinPer100:float, carbsPer100:float, fatPer100:float, kcalPer100:float}> $ingredients
     * @param array{protein:float, carbs:float, fat:float} $target
     * @return array{
     *     scalers: array<int, float>,
     *     balancedQuantities: array<int, float>,
     *     balancedMacros: array{protein:float, carbs:float, fat:float, kcal:float},
     *     iterations: int,
     * }
     */
    public function balance(array $ingredients, array $target): array
    {
        $count = count($ingredients);
        $scalers = array_fill(0, $count, 1.0);

        // Pin scalers for ingredients with no macro contribution (e.g. water) — gradient is zero anyway.
        $optimizable = [];
        foreach ($ingredients as $i => $ing) {
            if ($ing['proteinPer100'] + $ing['carbsPer100'] + $ing['fatPer100'] > 0) {
                $optimizable[] = $i;
            }
        }

        $iterations = 0;
        for (; $iterations < self::MAX_ITERATIONS; $iterations++) {
            $current = $this->computeMacros($ingredients, $scalers);

            $deviations = [
                'protein' => $current['protein'] - $target['protein'],
                'carbs' => $current['carbs'] - $target['carbs'],
                'fat' => $current['fat'] - $target['fat'],
            ];

            // Scaled loss = Σ (deviation / target)² so all macros contribute on equal footing.
            $gradMagnitudeSq = 0.0;
            $newScalers = $scalers;

            foreach ($optimizable as $i) {
                $ing = $ingredients[$i];
                $contribution = $ing['quantityG'] / 100.0;

                $grad = 0.0;
                foreach (['protein', 'carbs', 'fat'] as $macro) {
                    $tgt = max($target[$macro], 1e-6);
                    $perGramFactor = $ing[$macro . 'Per100'] * $contribution;
                    $grad += 2.0 * $deviations[$macro] / ($tgt * $tgt) * $perGramFactor;
                }

                $newScalers[$i] = max(
                    self::MIN_SCALER,
                    min(self::MAX_SCALER, $scalers[$i] - self::LEARNING_RATE * $grad),
                );
                $gradMagnitudeSq += $grad * $grad;
            }

            $scalers = $newScalers;

            if ($gradMagnitudeSq < self::CONVERGENCE_EPSILON) {
                break;
            }
        }

        $balancedQuantities = [];
        foreach ($ingredients as $i => $ing) {
            $balancedQuantities[$i] = round($ing['quantityG'] * $scalers[$i], 2);
        }

        $balancedMacros = $this->computeMacros($ingredients, $scalers);

        return [
            'scalers' => array_map(fn(float $s) => round($s, 4), $scalers),
            'balancedQuantities' => $balancedQuantities,
            'balancedMacros' => [
                'protein' => round($balancedMacros['protein'], 2),
                'carbs' => round($balancedMacros['carbs'], 2),
                'fat' => round($balancedMacros['fat'], 2),
                'kcal' => round($balancedMacros['kcal'], 2),
            ],
            'iterations' => $iterations,
        ];
    }

    /**
     * @return array{protein:float, carbs:float, fat:float, kcal:float}
     */
    public function computeMealMacros(Meal $meal): array
    {
        return $this->summarize($this->extractIngredients($meal));
    }

    /**
     * @param array<int, array{quantityG:float, proteinPer100:float, carbsPer100:float, fatPer100:float, kcalPer100:float}> $ingredients
     * @return array{protein:float, carbs:float, fat:float, kcal:float}
     */
    public function summarize(array $ingredients): array
    {
        $scalers = array_fill(0, count($ingredients), 1.0);
        $macros = $this->computeMacros($ingredients, $scalers);

        return [
            'protein' => round($macros['protein'], 2),
            'carbs' => round($macros['carbs'], 2),
            'fat' => round($macros['fat'], 2),
            'kcal' => round($macros['kcal'], 2),
        ];
    }

    /**
     * @return array<int, array{foodId:int, name:string, quantityG:float, proteinPer100:float, carbsPer100:float, fatPer100:float, kcalPer100:float, mealFoodId:int}>
     */
    public function extractIngredients(Meal $meal): array
    {
        $ingredients = [];
        foreach ($meal->getMealFoods() as $mf) {
            $food = $mf->getFood();
            $ingredients[] = [
                'mealFoodId' => $mf->getId(),
                'foodId' => $food->getId(),
                'name' => $food->getName(),
                'quantityG' => (float) $mf->getQuantityG(),
                'proteinPer100' => (float) $food->getProteinG(),
                'carbsPer100' => (float) $food->getCarbsG(),
                'fatPer100' => (float) $food->getFatG(),
                'kcalPer100' => (float) $food->getKcalPer100g(),
            ];
        }

        return $ingredients;
    }

    /**
     * @param array<int, array{quantityG:float, proteinPer100:float, carbsPer100:float, fatPer100:float, kcalPer100:float}> $ingredients
     * @param array<int, float> $scalers
     * @return array{protein:float, carbs:float, fat:float, kcal:float}
     */
    private function computeMacros(array $ingredients, array $scalers): array
    {
        $protein = $carbs = $fat = $kcal = 0.0;
        foreach ($ingredients as $i => $ing) {
            $effectiveG = $ing['quantityG'] * $scalers[$i] / 100.0;
            $protein += $ing['proteinPer100'] * $effectiveG;
            $carbs += $ing['carbsPer100'] * $effectiveG;
            $fat += $ing['fatPer100'] * $effectiveG;
            $kcal += $ing['kcalPer100'] * $effectiveG;
        }

        return ['protein' => $protein, 'carbs' => $carbs, 'fat' => $fat, 'kcal' => $kcal];
    }
}
