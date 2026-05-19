<?php

namespace App\Controller;

use App\Entity\Routine;
use App\Entity\User;
use App\Entity\UserRoutineSchedule;
use App\Enum\DayOfWeek;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users/me/schedule')]
class ScheduleController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['data' => $this->serializeWeek($user)]);
    }

    #[Route('/today', methods: ['GET'])]
    public function today(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $today = DayOfWeek::today();

        $entry = $this->findScheduleEntry($user, $today);
        if (!$entry) {
            return $this->json([
                'dayOfWeek' => $today->value,
                'routine' => null,
            ]);
        }

        return $this->json([
            'dayOfWeek' => $today->value,
            'routine' => $this->serializeRoutine($entry->getRoutine()),
        ]);
    }

    #[Route('/{day}', methods: ['PUT'], requirements: ['day' => '[a-z]+'])]
    public function set(string $day, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $dayEnum = DayOfWeek::tryFrom($day);
        if (!$dayEnum) {
            return $this->json(['error' => 'Invalid day. Must be one of: monday..sunday'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (!isset($data['routineId'])) {
            return $this->json(['error' => 'Field "routineId" is required'], Response::HTTP_BAD_REQUEST);
        }

        $routine = $em->getRepository(Routine::class)->find((int) $data['routineId']);
        if (!$routine) {
            return $this->json(['error' => 'Routine not found'], Response::HTTP_NOT_FOUND);
        }

        $entry = $this->findScheduleEntry($user, $dayEnum);
        if (!$entry) {
            $entry = new UserRoutineSchedule();
            $entry->setUser($user);
            $entry->setDayOfWeek($dayEnum);
            $em->persist($entry);
        }
        $entry->setRoutine($routine);
        $em->flush();

        return $this->json([
            'dayOfWeek' => $dayEnum->value,
            'routine' => $this->serializeRoutine($routine),
        ]);
    }

    #[Route('/{day}', methods: ['DELETE'], requirements: ['day' => '[a-z]+'])]
    public function clear(string $day, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $dayEnum = DayOfWeek::tryFrom($day);
        if (!$dayEnum) {
            return $this->json(['error' => 'Invalid day. Must be one of: monday..sunday'], Response::HTTP_BAD_REQUEST);
        }

        $entry = $this->findScheduleEntry($user, $dayEnum);
        if (!$entry) {
            return $this->json(null, Response::HTTP_NO_CONTENT);
        }

        $em->remove($entry);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function findScheduleEntry(User $user, DayOfWeek $day): ?UserRoutineSchedule
    {
        foreach ($user->getRoutineSchedules() as $entry) {
            if ($entry->getDayOfWeek() === $day) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * @return array<string, array{routineId: int, name: string, difficulty: string, goalType: ?string}|null>
     */
    private function serializeWeek(User $user): array
    {
        $week = [];
        foreach (DayOfWeek::cases() as $day) {
            $week[$day->value] = null;
        }
        foreach ($user->getRoutineSchedules() as $entry) {
            $week[$entry->getDayOfWeek()->value] = $this->serializeRoutine($entry->getRoutine());
        }
        return $week;
    }

    private function serializeRoutine(Routine $routine): array
    {
        return [
            'routineId' => $routine->getId(),
            'name' => $routine->getName(),
            'difficulty' => $routine->getDifficulty()->value,
            'goalType' => $routine->getGoalType()?->value,
        ];
    }
}
