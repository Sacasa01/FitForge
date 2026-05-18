<?php

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\SessionExercise;
use App\Entity\User;
use App\Entity\WorkoutSession;
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

    #[Route('/api/exercises', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));

        $qb = $em->getRepository(Exercise::class)->createQueryBuilder('e');

        if ($muscleGroup = $request->query->get('muscleGroup')) {
            $qb->andWhere('e.muscleGroup = :muscleGroup')->setParameter('muscleGroup', $muscleGroup);
        }
        if ($equipment = $request->query->get('equipment')) {
            $qb->andWhere('e.equipment = :equipment')->setParameter('equipment', $equipment);
        }
        if ($search = trim((string) $request->query->get('search', ''))) {
            $qb->andWhere('LOWER(e.name) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        $exercises = $qb->orderBy('e.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(fn(Exercise $e) => [
            'id' => $e->getId(),
            'name' => $e->getName(),
            'description' => $e->getDescription(),
            'muscleGroup' => $e->getMuscleGroup(),
            'equipment' => $e->getEquipment(),
            'caloriesPerMin' => $e->getCaloriesPerMin(),
        ], $exercises);

        return $this->json([
            'data' => $data,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

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

    #[Route('/api/exercises/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $exercise = $em->getRepository(Exercise::class)->find($id);
        if (!$exercise) {
            return $this->json(['error' => 'Exercise not found'], Response::HTTP_NOT_FOUND);
        }

        $historyLimit = min(50, max(1, (int) $request->query->get('historyLimit', 10)));

        $pr = $em->createQueryBuilder()
            ->select(
                'COUNT(DISTINCT ws.id) AS sessionCount',
                'MAX(es.weightKg) AS maxWeightKg',
                'MAX(es.reps) AS maxReps',
                'MAX(es.weightKg * es.reps) AS maxVolumeKgInSingleSet',
                'MAX(ws.startedAt) AS lastPerformedAt',
            )
            ->from(ExerciseSet::class, 'es')
            ->innerJoin('es.sessionExercise', 'se')
            ->innerJoin('se.session', 'ws')
            ->where('se.exercise = :exerciseId')
            ->andWhere('ws.user = :userId')
            ->setParameter('exerciseId', $id)
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleResult();

        $sessions = $em->getRepository(WorkoutSession::class)->createQueryBuilder('ws')
            ->innerJoin('ws.sessionExercises', 'se')
            ->where('ws.user = :userId')
            ->andWhere('se.exercise = :exerciseId')
            ->setParameter('userId', $user->getId())
            ->setParameter('exerciseId', $id)
            ->orderBy('ws.startedAt', 'DESC')
            ->setMaxResults($historyLimit)
            ->getQuery()
            ->getResult();

        $history = [];
        foreach ($sessions as $session) {
            /** @var WorkoutSession $session */
            foreach ($session->getSessionExercises() as $se) {
                if ($se->getExercise()?->getId() !== $id) {
                    continue;
                }

                $sets = array_map(fn(ExerciseSet $s) => [
                    'setNumber' => $s->getSetNumber(),
                    'reps' => $s->getReps(),
                    'weightKg' => $s->getWeightKg(),
                    'rpe' => $s->getRpe(),
                ], $se->getExerciseSets()->toArray());

                $history[] = [
                    'sessionId' => $session->getId(),
                    'performedAt' => $session->getStartedAt()->format('c'),
                    'enjoyment' => $se->getEnjoyment(),
                    'difficulty' => $se->getDifficulty(),
                    'sets' => $sets,
                ];
            }
        }

        return $this->json([
            'id' => $exercise->getId(),
            'name' => $exercise->getName(),
            'description' => $exercise->getDescription(),
            'muscleGroup' => $exercise->getMuscleGroup(),
            'equipment' => $exercise->getEquipment(),
            'caloriesPerMin' => $exercise->getCaloriesPerMin(),
            'personalRecords' => [
                'sessionCount' => (int) ($pr['sessionCount'] ?? 0),
                'maxWeightKg' => $pr['maxWeightKg'] !== null ? (float) $pr['maxWeightKg'] : null,
                'maxReps' => $pr['maxReps'] !== null ? (int) $pr['maxReps'] : null,
                'maxVolumeKgInSingleSet' => $pr['maxVolumeKgInSingleSet'] !== null
                    ? round((float) $pr['maxVolumeKgInSingleSet'], 2)
                    : null,
                'lastPerformedAt' => !empty($pr['lastPerformedAt'])
                    ? (new \DateTime($pr['lastPerformedAt']))->format('c')
                    : null,
            ],
            'history' => $history,
        ]);
    }
}
