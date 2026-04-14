<?php

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\Routine;
use App\Entity\SessionExercise;
use App\Entity\User;
use App\Entity\WorkoutSession;
use App\Enum\GeneralFeeling;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/workouts')]
class WorkoutController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $session = new WorkoutSession();
        $session->setUser($user);
        $session->setStartedAt(
            isset($data['startedAt']) ? new \DateTime($data['startedAt']) : new \DateTime(),
        );

        if (!empty($data['routineId'])) {
            $routine = $em->getRepository(Routine::class)->find((int) $data['routineId']);
            if (!$routine) {
                return $this->json(['error' => 'Routine not found'], Response::HTTP_NOT_FOUND);
            }
            $session->setRoutine($routine);
        }

        if (isset($data['notes'])) {
            $session->setNotes($data['notes']);
        }

        $em->persist($session);
        $em->flush();

        return $this->json([
            'id' => $session->getId(),
            'startedAt' => $session->getStartedAt()->format('c'),
            'routineId' => $session->getRoutine()?->getId(),
            'notes' => $session->getNotes(),
        ], Response::HTTP_CREATED);
    }

    #[Route('', methods: ['GET'])]
    public function history(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $repo = $em->getRepository(WorkoutSession::class);
        $total = $repo->count(['user' => $user]);

        $sessions = $repo->findBy(
            ['user' => $user],
            ['startedAt' => 'DESC'],
            $limit,
            $offset,
        );

        $items = array_map(fn(WorkoutSession $s) => [
            'id' => $s->getId(),
            'startedAt' => $s->getStartedAt()->format('c'),
            'durationMin' => $s->getDurationMin(),
            'generalFeeling' => $s->getGeneralFeeling()?->value,
            'notes' => $s->getNotes(),
            'routineId' => $s->getRoutine()?->getId(),
            'exerciseCount' => $s->getSessionExercises()->count(),
        ], $sessions);

        return $this->json([
            'data' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    #[Route('/{id}/exercises', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addExercise(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $session = $this->findSessionForCurrentUser($id, $em);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['exerciseId'])) {
            return $this->json(['error' => 'Field "exerciseId" is required'], Response::HTTP_BAD_REQUEST);
        }

        $exercise = $em->getRepository(Exercise::class)->find((int) $data['exerciseId']);
        if (!$exercise) {
            return $this->json(['error' => 'Exercise not found'], Response::HTTP_NOT_FOUND);
        }

        $sessionExercise = new SessionExercise();
        $sessionExercise->setSession($session);
        $sessionExercise->setExercise($exercise);
        $sessionExercise->setOrderIndex((int) ($data['orderIndex'] ?? $session->getSessionExercises()->count()));

        $em->persist($sessionExercise);
        $em->flush();

        return $this->json([
            'id' => $sessionExercise->getId(),
            'exerciseId' => $exercise->getId(),
            'exerciseName' => $exercise->getName(),
            'orderIndex' => $sessionExercise->getOrderIndex(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/exercises/{exId}/sets', methods: ['POST'], requirements: ['id' => '\d+', 'exId' => '\d+'])]
    public function addSet(int $id, int $exId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $sessionExercise = $this->findSessionExerciseForCurrentUser($id, $exId, $em);
        if ($sessionExercise instanceof JsonResponse) {
            return $sessionExercise;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $set = new ExerciseSet();
        $set->setSessionExercise($sessionExercise);
        $set->setSetNumber((int) ($data['setNumber'] ?? $sessionExercise->getExerciseSets()->count() + 1));

        if (isset($data['reps'])) {
            $set->setReps((int) $data['reps']);
        }
        if (isset($data['weightKg'])) {
            $set->setWeightKg((string) $data['weightKg']);
        }
        if (isset($data['rpe'])) {
            $rpe = (int) $data['rpe'];
            if ($rpe < 1 || $rpe > 10) {
                return $this->json(['error' => 'RPE must be between 1 and 10'], Response::HTTP_BAD_REQUEST);
            }
            $set->setRpe($rpe);
        }

        $em->persist($set);
        $em->flush();

        return $this->json([
            'id' => $set->getId(),
            'setNumber' => $set->getSetNumber(),
            'reps' => $set->getReps(),
            'weightKg' => $set->getWeightKg(),
            'rpe' => $set->getRpe(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/exercises/{exId}', methods: ['PUT'], requirements: ['id' => '\d+', 'exId' => '\d+'])]
    public function rateExercise(int $id, int $exId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $sessionExercise = $this->findSessionExerciseForCurrentUser($id, $exId, $em);
        if ($sessionExercise instanceof JsonResponse) {
            return $sessionExercise;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['enjoyment'])) {
            $enjoyment = (int) $data['enjoyment'];
            if ($enjoyment < 1 || $enjoyment > 5) {
                return $this->json(['error' => 'Enjoyment must be between 1 and 5'], Response::HTTP_BAD_REQUEST);
            }
            $sessionExercise->setEnjoyment($enjoyment);
        }

        if (isset($data['difficulty'])) {
            $difficulty = (int) $data['difficulty'];
            if ($difficulty < 1 || $difficulty > 5) {
                return $this->json(['error' => 'Difficulty must be between 1 and 5'], Response::HTTP_BAD_REQUEST);
            }
            $sessionExercise->setDifficulty($difficulty);
        }

        $em->flush();

        return $this->json([
            'id' => $sessionExercise->getId(),
            'enjoyment' => $sessionExercise->getEnjoyment(),
            'difficulty' => $sessionExercise->getDifficulty(),
        ]);
    }

    #[Route('/{id}/finish', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function finish(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $session = $this->findSessionForCurrentUser($id, $em);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['durationMin'])) {
            $session->setDurationMin((int) $data['durationMin']);
        } elseif ($session->getStartedAt()) {
            $diff = (new \DateTime())->getTimestamp() - $session->getStartedAt()->getTimestamp();
            $session->setDurationMin((int) max(0, round($diff / 60)));
        }

        if (isset($data['generalFeeling'])) {
            $feeling = GeneralFeeling::tryFrom($data['generalFeeling']);
            if (!$feeling) {
                return $this->json([
                    'error' => 'Invalid generalFeeling. Must be one of: bad, regular, good, great',
                ], Response::HTTP_BAD_REQUEST);
            }
            $session->setGeneralFeeling($feeling);
        }

        if (isset($data['notes'])) {
            $session->setNotes($data['notes']);
        }

        $em->flush();

        return $this->json([
            'id' => $session->getId(),
            'durationMin' => $session->getDurationMin(),
            'generalFeeling' => $session->getGeneralFeeling()?->value,
            'notes' => $session->getNotes(),
        ]);
    }

    private function findSessionForCurrentUser(int $id, EntityManagerInterface $em): WorkoutSession|JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $session = $em->getRepository(WorkoutSession::class)->find($id);
        if (!$session) {
            return $this->json(['error' => 'Workout session not found'], Response::HTTP_NOT_FOUND);
        }
        if ($session->getUser()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $session;
    }

    private function findSessionExerciseForCurrentUser(int $sessionId, int $exId, EntityManagerInterface $em): SessionExercise|JsonResponse
    {
        $session = $this->findSessionForCurrentUser($sessionId, $em);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        $sessionExercise = $em->getRepository(SessionExercise::class)->find($exId);
        if (!$sessionExercise || $sessionExercise->getSession()?->getId() !== $session->getId()) {
            return $this->json(['error' => 'Session exercise not found'], Response::HTTP_NOT_FOUND);
        }

        return $sessionExercise;
    }
}
