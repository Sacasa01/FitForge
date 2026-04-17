<?php

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\SessionExercise;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ExerciseController extends AbstractController
{
    private const PERSONAL_WEIGHT = 0.6;
    private const GLOBAL_WEIGHT = 0.4;
    private const DEFAULT_GLOBAL_SCORE = 2.5;

    #[Route('/api/exercises/recommend', methods: ['GET'])]
    public function recommend(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $muscleGroup = $request->query->get('muscleGroup');
        if (!$muscleGroup) {
            return $this->json(['error' => 'muscleGroup parameter is required'], Response::HTTP_BAD_REQUEST);
        }

        $equipment = $request->query->get('equipment');
        $limit = min(20, max(1, (int) $request->query->get('limit', 5)));

        $globalQb = $em->createQueryBuilder()
            ->select(
                'e.id AS id',
                'e.name AS name',
                'e.muscleGroup AS muscleGroup',
                'e.equipment AS equipment',
                'e.description AS description',
                'AVG(se.enjoyment) AS globalAvg',
            )
            ->from(Exercise::class, 'e')
            ->leftJoin(SessionExercise::class, 'se', 'WITH', 'se.exercise = e AND se.enjoyment IS NOT NULL')
            ->where('e.muscleGroup = :muscleGroup')
            ->groupBy('e.id, e.name, e.muscleGroup, e.equipment, e.description')
            ->setParameter('muscleGroup', $muscleGroup);

        if ($equipment) {
            $globalQb->andWhere('e.equipment = :equipment')
                ->setParameter('equipment', $equipment);
        }

        $rows = $globalQb->getQuery()->getArrayResult();

        if (empty($rows)) {
            return $this->json(['error' => 'No exercises found for this muscle group'], Response::HTTP_NOT_FOUND);
        }

        $exerciseIds = array_map(fn(array $r) => (int) $r['id'], $rows);

        $personalRows = $em->createQueryBuilder()
            ->select('IDENTITY(se.exercise) AS exerciseId', 'AVG(se.enjoyment) AS personalAvg')
            ->from(SessionExercise::class, 'se')
            ->innerJoin('se.session', 'ws')
            ->where('ws.user = :userId')
            ->andWhere('se.exercise IN (:exerciseIds)')
            ->andWhere('se.enjoyment IS NOT NULL')
            ->groupBy('se.exercise')
            ->setParameter('userId', $user->getId())
            ->setParameter('exerciseIds', $exerciseIds)
            ->getQuery()
            ->getArrayResult();

        $personalByExercise = [];
        foreach ($personalRows as $pr) {
            $personalByExercise[(int) $pr['exerciseId']] = (float) $pr['personalAvg'];
        }

        $scored = array_map(function (array $row) use ($personalByExercise) {
            $exerciseId = (int) $row['id'];
            $personal = $personalByExercise[$exerciseId] ?? null;
            $global = $row['globalAvg'] !== null ? (float) $row['globalAvg'] : self::DEFAULT_GLOBAL_SCORE;
            $score = $personal !== null
                ? $personal * self::PERSONAL_WEIGHT + $global * self::GLOBAL_WEIGHT
                : $global;

            return [
                'id' => $exerciseId,
                'name' => $row['name'],
                'muscleGroup' => $row['muscleGroup'],
                'equipment' => $row['equipment'],
                'description' => $row['description'],
                'recommendationScore' => round($score, 2),
                'personalScore' => $personal !== null ? round($personal, 2) : null,
                'globalScore' => round($global, 2),
            ];
        }, $rows);

        usort($scored, fn($a, $b) => $b['recommendationScore'] <=> $a['recommendationScore']);

        return $this->json(['data' => array_slice($scored, 0, $limit)]);
    }
}
