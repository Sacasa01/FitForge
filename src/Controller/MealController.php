<?php

namespace App\Controller;

use App\Entity\Meal;
use App\Entity\MealFood;
use App\Entity\MealLog;
use App\Entity\User;
use App\Entity\UserMealFoodOverride;
use App\Repository\UserMealFoodOverrideRepository;
use App\Service\MealBalancer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MealController extends AbstractController
{
    private const PERSONAL_WEIGHT = 0.6;
    private const GLOBAL_WEIGHT = 0.4;
    private const DEFAULT_GLOBAL_SCORE = 2.5;

    private const MEAL_TIME_SLOTS = [
        'breakfast' => ['05:00:00', '10:59:59'],
        'lunch' => ['11:00:00', '15:59:59'],
        'dinner' => ['16:00:00', '23:59:59'],
    ];

    #[Route('/api/meals/recommend', methods: ['GET'])]
    public function recommend(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $mealTime = $request->query->get('mealTime');
        if (!$mealTime) {
            return $this->json(['error' => 'mealTime parameter is required'], Response::HTTP_BAD_REQUEST);
        }
        if (!isset(self::MEAL_TIME_SLOTS[$mealTime])) {
            return $this->json([
                'error' => 'Invalid mealTime. Must be one of: ' . implode(', ', array_keys(self::MEAL_TIME_SLOTS)),
            ], Response::HTTP_BAD_REQUEST);
        }

        [$from, $to] = self::MEAL_TIME_SLOTS[$mealTime];
        $limit = min(20, max(1, (int) $request->query->get('limit', 5)));

        $globalQb = $em->createQueryBuilder()
            ->select(
                'm.id AS id',
                'm.name AS name',
                'm.mealTime AS mealTime',
                'd.id AS dietId',
                'd.name AS dietName',
                'AVG(ml.enjoyment) AS globalEnjoyment',
                'AVG(ml.satiety) AS globalSatiety',
            )
            ->from(Meal::class, 'm')
            ->innerJoin('m.diet', 'd')
            ->leftJoin(
                MealLog::class,
                'ml',
                'WITH',
                'ml.meal = m AND ml.enjoyment IS NOT NULL AND ml.satiety IS NOT NULL',
            )
            ->where('m.mealTime BETWEEN :from AND :to')
            ->groupBy('m.id, m.name, m.mealTime, d.id, d.name')
            ->setParameter('from', new \DateTime($from))
            ->setParameter('to', new \DateTime($to));

        if ($dietIdParam = $request->query->get('dietId')) {
            $globalQb->andWhere('d.id = :dietId')
                ->setParameter('dietId', (int) $dietIdParam);
        }

        $rows = $globalQb->getQuery()->getArrayResult();

        if (empty($rows)) {
            return $this->json(['error' => 'No meals found for this time slot'], Response::HTTP_NOT_FOUND);
        }

        $mealIds = array_map(fn(array $r) => (int) $r['id'], $rows);

        $personalRows = $em->createQueryBuilder()
            ->select(
                'IDENTITY(ml.meal) AS mealId',
                'AVG(ml.enjoyment) AS personalEnjoyment',
                'AVG(ml.satiety) AS personalSatiety',
            )
            ->from(MealLog::class, 'ml')
            ->innerJoin('ml.dietLog', 'dl')
            ->where('dl.user = :userId')
            ->andWhere('ml.meal IN (:mealIds)')
            ->andWhere('ml.enjoyment IS NOT NULL')
            ->andWhere('ml.satiety IS NOT NULL')
            ->groupBy('ml.meal')
            ->setParameter('userId', $user->getId())
            ->setParameter('mealIds', $mealIds)
            ->getQuery()
            ->getArrayResult();

        $personalByMeal = [];
        foreach ($personalRows as $pr) {
            $personalByMeal[(int) $pr['mealId']] = [
                'enjoyment' => (float) $pr['personalEnjoyment'],
                'satiety' => (float) $pr['personalSatiety'],
            ];
        }

        $scored = array_map(function (array $row) use ($personalByMeal) {
            $mealId = (int) $row['id'];

            $globalEnjoyment = $row['globalEnjoyment'] !== null ? (float) $row['globalEnjoyment'] : self::DEFAULT_GLOBAL_SCORE;
            $globalSatiety = $row['globalSatiety'] !== null ? (float) $row['globalSatiety'] : self::DEFAULT_GLOBAL_SCORE;
            $globalCombined = ($globalEnjoyment + $globalSatiety) / 2;

            $personal = $personalByMeal[$mealId] ?? null;
            $personalCombined = $personal !== null
                ? ($personal['enjoyment'] + $personal['satiety']) / 2
                : null;

            $score = $personalCombined !== null
                ? $personalCombined * self::PERSONAL_WEIGHT + $globalCombined * self::GLOBAL_WEIGHT
                : $globalCombined;

            $mealTimeRaw = $row['mealTime'];
            $mealTimeStr = $mealTimeRaw instanceof \DateTimeInterface
                ? $mealTimeRaw->format('H:i')
                : (is_string($mealTimeRaw) ? substr($mealTimeRaw, 0, 5) : null);

            return [
                'id' => $mealId,
                'name' => $row['name'],
                'mealTime' => $mealTimeStr,
                'diet' => [
                    'id' => (int) $row['dietId'],
                    'name' => $row['dietName'],
                ],
                'recommendationScore' => round($score, 2),
                'personalEnjoyment' => $personal !== null ? round($personal['enjoyment'], 2) : null,
                'personalSatiety' => $personal !== null ? round($personal['satiety'], 2) : null,
                'globalEnjoyment' => round($globalEnjoyment, 2),
                'globalSatiety' => round($globalSatiety, 2),
            ];
        }, $rows);

        usort($scored, fn($a, $b) => $b['recommendationScore'] <=> $a['recommendationScore']);

        return $this->json(['data' => array_slice($scored, 0, $limit)]);
    }

    #[Route('/api/meals/{id}/balance', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function balance(
        int $id,
        EntityManagerInterface $em,
        MealBalancer $balancer,
        UserMealFoodOverrideRepository $overrideRepo,
    ): JsonResponse {
        $result = $this->buildBalancePlan($id, $em, $balancer, $overrideRepo);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        return $this->json($result['payload']);
    }

    #[Route('/api/meals/{id}/balance/apply', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function applyBalance(
        int $id,
        EntityManagerInterface $em,
        MealBalancer $balancer,
        UserMealFoodOverrideRepository $overrideRepo,
    ): JsonResponse {
        $result = $this->buildBalancePlan($id, $em, $balancer, $overrideRepo);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        /** @var User $user */
        $user = $this->getUser();

        $mealFoodById = [];
        foreach ($result['meal']->getMealFoods() as $mf) {
            $mealFoodById[$mf->getId()] = $mf;
        }

        $existingByMealFoodId = [];
        $existing = $overrideRepo->findBy([
            'user' => $user,
            'mealFood' => array_keys($mealFoodById),
        ]);
        foreach ($existing as $o) {
            $existingByMealFoodId[$o->getMealFood()->getId()] = $o;
        }

        foreach ($result['payload']['foods'] as $foodPlan) {
            $mealFood = $mealFoodById[$foodPlan['mealFoodId']] ?? null;
            if ($mealFood === null) {
                continue;
            }

            $override = $existingByMealFoodId[$mealFood->getId()] ?? null;
            if ($override === null) {
                $override = (new UserMealFoodOverride())
                    ->setUser($user)
                    ->setMealFood($mealFood);
                $em->persist($override);
            }
            $override->setQuantityG((string) $foodPlan['balancedQuantityG']);
        }

        $em->flush();

        return $this->json($result['payload'] + ['applied' => true]);
    }

    /**
     * @return JsonResponse|array{meal: Meal, payload: array<string, mixed>}
     */
    private function buildBalancePlan(
        int $id,
        EntityManagerInterface $em,
        MealBalancer $balancer,
        UserMealFoodOverrideRepository $overrideRepo,
    ): JsonResponse|array {
        /** @var User $user */
        $user = $this->getUser();

        if (
            $user->getDailyProteinG() === null
            || $user->getDailyCarbsG() === null
            || $user->getDailyFatG() === null
        ) {
            return $this->json([
                'error' => 'User daily macro targets are not set. PUT /api/users/me/macros first.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $meal = $em->getRepository(Meal::class)->find($id);
        if (!$meal) {
            return $this->json(['error' => 'Meal not found'], Response::HTTP_NOT_FOUND);
        }

        $diet = $meal->getDiet();
        $dietMeals = $diet->getMeals();

        $allMealFoodIds = [];
        foreach ($dietMeals as $m) {
            foreach ($m->getMealFoods() as $mf) {
                $allMealFoodIds[] = $mf->getId();
            }
        }

        $overrides = $overrideRepo->loadOverridesMap($user, $allMealFoodIds);

        $applyOverrides = static function (array $ingredients) use ($overrides): array {
            foreach ($ingredients as &$ing) {
                if (isset($overrides[$ing['mealFoodId']])) {
                    $ing['quantityG'] = $overrides[$ing['mealFoodId']];
                }
            }
            return $ingredients;
        };

        $ingredients = $applyOverrides($balancer->extractIngredients($meal));
        if (empty($ingredients)) {
            return $this->json(['error' => 'Meal has no foods to balance'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $current = $balancer->summarize($ingredients);

        $dietTotalKcal = 0.0;
        foreach ($dietMeals as $m) {
            $dietTotalKcal += $balancer->summarize($applyOverrides($balancer->extractIngredients($m)))['kcal'];
        }

        if ($dietTotalKcal <= 0) {
            return $this->json([
                'error' => 'Diet has no caloric content; cannot compute meal share',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $share = $current['kcal'] / $dietTotalKcal;

        $target = [
            'protein' => (float) $user->getDailyProteinG() * $share,
            'carbs' => (float) $user->getDailyCarbsG() * $share,
            'fat' => (float) $user->getDailyFatG() * $share,
        ];
        $targetKcal = $target['protein'] * 4 + $target['carbs'] * 4 + $target['fat'] * 9;

        $balanced = $balancer->balance($ingredients, $target);

        $foodsPayload = [];
        foreach ($ingredients as $i => $ing) {
            $foodsPayload[] = [
                'mealFoodId' => $ing['mealFoodId'],
                'foodId' => $ing['foodId'],
                'name' => $ing['name'],
                'originalQuantityG' => round($ing['quantityG'], 2),
                'balancedQuantityG' => $balanced['balancedQuantities'][$i],
                'scaler' => $balanced['scalers'][$i],
            ];
        }

        $payload = [
            'mealId' => $meal->getId(),
            'mealName' => $meal->getName(),
            'shareOfDay' => round($share, 4),
            'targetMacros' => [
                'protein' => round($target['protein'], 2),
                'carbs' => round($target['carbs'], 2),
                'fat' => round($target['fat'], 2),
                'kcal' => round($targetKcal, 2),
            ],
            'currentMacros' => $current,
            'balancedMacros' => $balanced['balancedMacros'],
            'foods' => $foodsPayload,
            'iterations' => $balanced['iterations'],
        ];

        return ['meal' => $meal, 'payload' => $payload];
    }

    #[Route('/api/meals/{id}/balance', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function clearBalance(
        int $id,
        EntityManagerInterface $em,
        UserMealFoodOverrideRepository $overrideRepo,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $meal = $em->getRepository(Meal::class)->find($id);
        if (!$meal) {
            return $this->json(['error' => 'Meal not found'], Response::HTTP_NOT_FOUND);
        }

        $mealFoodIds = [];
        foreach ($meal->getMealFoods() as $mf) {
            $mealFoodIds[] = $mf->getId();
        }

        if (empty($mealFoodIds)) {
            return $this->json(['cleared' => 0]);
        }

        $cleared = $em->createQueryBuilder()
            ->delete(UserMealFoodOverride::class, 'o')
            ->where('o.user = :user')
            ->andWhere('o.mealFood IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $mealFoodIds)
            ->getQuery()
            ->execute();

        return $this->json(['cleared' => (int) $cleared]);
    }
}
