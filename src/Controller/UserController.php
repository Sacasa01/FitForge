<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserWeightLog;
use App\Enum\GoalType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
class UserController extends AbstractController
{
    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/me', methods: ['PUT'])]
    public function update(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (isset($data['birthdate'])) {
            $user->setBirthdate(new \DateTime($data['birthdate']));
        }
        if (isset($data['heightCm'])) {
            $user->setHeightCm($data['heightCm']);
        }
        if (isset($data['currentWeightKg'])) {
            $user->setCurrentWeightKg($data['currentWeightKg']);
        }
        if (isset($data['goalType'])) {
            $goalType = GoalType::tryFrom($data['goalType']);
            if (!$goalType) {
                return $this->json([
                    'error' => 'Invalid goalType. Must be one of: weight_loss, muscle_gain, maintenance, endurance',
                ], Response::HTTP_BAD_REQUEST);
            }
            $user->setGoalType($goalType);
        }

        $em->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/me/weight', methods: ['POST'])]
    public function logWeight(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['weightKg'])) {
            return $this->json(['error' => 'Field "weightKg" is required'], Response::HTTP_BAD_REQUEST);
        }

        $weight = (float) $data['weightKg'];
        if ($weight < 20 || $weight > 300) {
            return $this->json(['error' => 'Weight must be between 20 and 300 kg'], Response::HTTP_BAD_REQUEST);
        }

        $log = new UserWeightLog();
        $log->setUser($user);
        $log->setWeightKg((string) $weight);

        if (isset($data['loggedAt'])) {
            $log->setLoggedAt(new \DateTime($data['loggedAt']));
        }
        if (isset($data['notes'])) {
            $log->setNotes($data['notes']);
        }

        $user->setCurrentWeightKg((string) $weight);

        $em->persist($log);
        $em->flush();

        return $this->json([
            'id' => $log->getId(),
            'weightKg' => $log->getWeightKg(),
            'loggedAt' => $log->getLoggedAt()->format('Y-m-d'),
            'notes' => $log->getNotes(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/me/weight', methods: ['GET'])]
    public function weightHistory(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $repo = $em->getRepository(UserWeightLog::class);

        $total = $repo->count(['user' => $user]);

        $logs = $repo->findBy(
            ['user' => $user],
            ['loggedAt' => 'DESC'],
            $limit,
            $offset,
        );

        $items = array_map(fn(UserWeightLog $log) => [
            'id' => $log->getId(),
            'weightKg' => $log->getWeightKg(),
            'loggedAt' => $log->getLoggedAt()->format('Y-m-d'),
            'notes' => $log->getNotes(),
        ], $logs);

        return $this->json([
            'data' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'birthdate' => $user->getBirthdate()?->format('Y-m-d'),
            'heightCm' => $user->getHeightCm(),
            'currentWeightKg' => $user->getCurrentWeightKg(),
            'goalType' => $user->getGoalType()?->value,
            'role' => $user->getRole()->value,
            'assignedRoutineId' => $user->getAssignedRoutine()?->getId(),
            'assignedDietId' => $user->getAssignedDiet()?->getId(),
            'createdAt' => $user->getCreatedAt()->format('c'),
        ];
    }
}
