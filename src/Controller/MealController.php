<?php

namespace App\Controller;

use App\Entity\Meal;
use App\Entity\MealLog;
use App\Entity\User;
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
}
