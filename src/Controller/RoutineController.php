<?php

namespace App\Controller;

use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Enum\GoalType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RoutineController extends AbstractController
{
    #[Route('/api/routines', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $criteria = [];

        if ($goalTypeParam = $request->query->get('goal_type')) {
            $goalType = GoalType::tryFrom($goalTypeParam);
            if (!$goalType) {
                return $this->json([
                    'error' => 'Invalid goal_type. Must be one of: weight_loss, muscle_gain, maintenance, endurance',
                ], Response::HTTP_BAD_REQUEST);
            }
            $criteria['goalType'] = $goalType;
        }

        $routines = $em->getRepository(Routine::class)->findBy($criteria, ['id' => 'ASC']);

        $data = array_map(fn(Routine $r) => [
            'id' => $r->getId(),
            'name' => $r->getName(),
            'description' => $r->getDescription(),
            'difficulty' => $r->getDifficulty()->value,
            'goalType' => $r->getGoalType()?->value,
            'exerciseCount' => $r->getRoutineExercises()->count(),
        ], $routines);

        return $this->json(['data' => $data]);
    }

    #[Route('/api/routines/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id, EntityManagerInterface $em): JsonResponse
    {
        $routine = $em->getRepository(Routine::class)->find($id);
        if (!$routine) {
            return $this->json(['error' => 'Routine not found'], Response::HTTP_NOT_FOUND);
        }

        $exercises = array_map(fn(RoutineExercise $re) => [
            'id' => $re->getId(),
            'exercise' => [
                'id' => $re->getExercise()->getId(),
                'name' => $re->getExercise()->getName(),
                'muscleGroup' => $re->getExercise()->getMuscleGroup(),
                'equipment' => $re->getExercise()->getEquipment(),
            ],
            'sets' => $re->getSets(),
            'reps' => $re->getReps(),
            'orderIndex' => $re->getOrderIndex(),
        ], $routine->getRoutineExercises()->toArray());

        return $this->json([
            'id' => $routine->getId(),
            'name' => $routine->getName(),
            'description' => $routine->getDescription(),
            'difficulty' => $routine->getDifficulty()->value,
            'goalType' => $routine->getGoalType()?->value,
            'exercises' => $exercises,
        ]);
    }

    #[Route('/api/users/me/assign-routine', methods: ['POST'])]
    public function assign(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['routineId'])) {
            return $this->json(['error' => 'Field "routineId" is required'], Response::HTTP_BAD_REQUEST);
        }

        $routine = $em->getRepository(Routine::class)->find((int) $data['routineId']);
        if (!$routine) {
            return $this->json(['error' => 'Routine not found'], Response::HTTP_NOT_FOUND);
        }

        $user->setAssignedRoutine($routine);
        $em->flush();

        return $this->json([
            'message' => 'Routine assigned successfully',
            'assignedRoutineId' => $routine->getId(),
        ]);
    }
}
