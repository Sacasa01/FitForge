<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserWeightLog;
use App\Enum\ActivityLevel;
use App\Enum\GoalType;
use App\Service\NutritionCalculator;
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
        if (array_key_exists('activityLevel', $data)) {
            if ($data['activityLevel'] === null) {
                $user->setActivityLevel(null);
            } else {
                $activityLevel = ActivityLevel::tryFrom($data['activityLevel']);
                if (!$activityLevel) {
                    return $this->json([
                        'error' => 'Invalid activityLevel. Must be one of: sedentary, light, moderate, active, very_active',
                    ], Response::HTTP_BAD_REQUEST);
                }
                $user->setActivityLevel($activityLevel);
            }
        }

        $em->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/me/macros', methods: ['PUT'])]
    public function setMacros(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        foreach (['dailyProteinG', 'dailyCarbsG', 'dailyFatG'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($value === null) {
                $user->{'set' . ucfirst($field)}(null);
                continue;
            }
            $float = (float) $value;
            if ($float < 0 || $float > 9999) {
                return $this->json(['error' => sprintf('%s must be between 0 and 9999', $field)], Response::HTTP_BAD_REQUEST);
            }
            $user->{'set' . ucfirst($field)}((string) $float);
        }

        $em->flush();

        return $this->json([
            'dailyProteinG' => $user->getDailyProteinG(),
            'dailyCarbsG' => $user->getDailyCarbsG(),
            'dailyFatG' => $user->getDailyFatG(),
        ]);
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

    #[Route('/me/nutrition', methods: ['GET'])]
    public function nutrition(Request $request, NutritionCalculator $calculator): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $bodyFatPercent = null;
        if ($request->query->has('bodyFatPercent')) {
            $bodyFatPercent = (float) $request->query->get('bodyFatPercent');
            if ($bodyFatPercent < 2 || $bodyFatPercent > 60) {
                return $this->json(['error' => 'bodyFatPercent must be between 2 and 60'], Response::HTTP_BAD_REQUEST);
            }
        }

        $activityOverride = null;
        if ($request->query->has('activityLevel')) {
            $activityOverride = ActivityLevel::tryFrom((string) $request->query->get('activityLevel'));
            if (!$activityOverride) {
                return $this->json([
                    'error' => 'Invalid activityLevel. Must be one of: sedentary, light, moderate, active, very_active',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $sex = $request->query->has('sex') ? (string) $request->query->get('sex') : null;
        if ($sex !== null && !in_array($sex, ['male', 'female'], true)) {
            return $this->json(['error' => 'sex must be "male" or "female"'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $profile = $calculator->profileFor($user, $bodyFatPercent, $activityOverride, $sex);
        } catch (\DomainException | \InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($profile);
    }

    #[Route('/me/nutrition/apply', methods: ['POST'])]
    public function applyNutrition(
        Request $request,
        NutritionCalculator $calculator,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $bodyFatPercent = isset($data['bodyFatPercent']) ? (float) $data['bodyFatPercent'] : null;
        if ($bodyFatPercent !== null && ($bodyFatPercent < 2 || $bodyFatPercent > 60)) {
            return $this->json(['error' => 'bodyFatPercent must be between 2 and 60'], Response::HTTP_BAD_REQUEST);
        }

        $activityOverride = null;
        if (isset($data['activityLevel'])) {
            $activityOverride = ActivityLevel::tryFrom((string) $data['activityLevel']);
            if (!$activityOverride) {
                return $this->json([
                    'error' => 'Invalid activityLevel. Must be one of: sedentary, light, moderate, active, very_active',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $sex = isset($data['sex']) ? (string) $data['sex'] : null;
        if ($sex !== null && !in_array($sex, ['male', 'female'], true)) {
            return $this->json(['error' => 'sex must be "male" or "female"'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $profile = $calculator->profileFor($user, $bodyFatPercent, $activityOverride, $sex);
        } catch (\DomainException | \InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $user->setDailyProteinG((string) $profile['macros']['proteinG']);
        $user->setDailyCarbsG((string) $profile['macros']['carbsG']);
        $user->setDailyFatG((string) $profile['macros']['fatG']);
        $user->setDailyKcal((string) $profile['macros']['kcalAdjusted']);
        $user->setBodyFatPercent($bodyFatPercent !== null ? (string) $bodyFatPercent : null);
        $user->setBmi((string) $profile['bmi']);
        $user->setBmr((string) $profile['bmr']);
        $user->setTdee((string) $profile['tdee']);
        if ($activityOverride !== null) {
            $user->setActivityLevel($activityOverride);
        }
        $user->setNutritionCalculatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json($profile);
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
            'activityLevel' => $user->getActivityLevel()?->value,
            'role' => $user->getRole()->value,
            'assignedRoutineId' => $user->getAssignedRoutine()?->getId(),
            'assignedDietId' => $user->getAssignedDiet()?->getId(),
            'dailyProteinG' => $user->getDailyProteinG(),
            'dailyCarbsG' => $user->getDailyCarbsG(),
            'dailyFatG' => $user->getDailyFatG(),
            'dailyKcal' => $user->getDailyKcal(),
            'bodyFatPercent' => $user->getBodyFatPercent(),
            'bmi' => $user->getBmi(),
            'bmr' => $user->getBmr(),
            'tdee' => $user->getTdee(),
            'nutritionCalculatedAt' => $user->getNutritionCalculatedAt()?->format('c'),
            'createdAt' => $user->getCreatedAt()->format('c'),
        ];
    }
}
