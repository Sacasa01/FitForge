<?php

namespace App\Controller;

use App\Entity\Diet;
use App\Entity\Exercise;
use App\Entity\Food;
use App\Entity\Meal;
use App\Entity\MealFood;
use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Enum\Difficulty;
use App\Enum\GoalType;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    // ---- Users ----

    #[Route('/users', methods: ['GET'])]
    public function listUsers(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));
        $offset = ($page - 1) * $limit;

        $repo = $em->getRepository(User::class);
        $total = $repo->count([]);
        $users = $repo->findBy([], ['id' => 'ASC'], $limit, $offset);

        return $this->json([
            'data' => array_map([$this, 'serializeUser'], $users),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    #[Route('/users/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getUserById(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/users/{id}/role', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function changeRole(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['role'])) {
            return $this->json(['error' => 'Field "role" is required'], Response::HTTP_BAD_REQUEST);
        }

        $role = Role::tryFrom($data['role']);
        if (!$role) {
            return $this->json(['error' => 'Invalid role. Must be one of: admin, user'], Response::HTTP_BAD_REQUEST);
        }

        $user->setRole($role);
        $em->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/users/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteUser(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }
        if ($user->getId() === $currentUser->getId()) {
            return $this->json(['error' => 'Cannot delete your own account'], Response::HTTP_BAD_REQUEST);
        }

        $em->remove($user);
        $em->flush();

        return $this->json(['message' => 'User deleted']);
    }

    // ---- Exercises ----

    #[Route('/exercises', methods: ['POST'])]
    public function createExercise(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name'])) {
            return $this->json(['error' => 'Field "name" is required'], Response::HTTP_BAD_REQUEST);
        }

        $exercise = new Exercise();
        $this->applyExerciseFields($exercise, $data);

        $em->persist($exercise);
        $em->flush();

        return $this->json($this->serializeExercise($exercise), Response::HTTP_CREATED);
    }

    #[Route('/exercises/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateExercise(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $exercise = $em->getRepository(Exercise::class)->find($id);
        if (!$exercise) {
            return $this->json(['error' => 'Exercise not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->applyExerciseFields($exercise, $data);
        $em->flush();

        return $this->json($this->serializeExercise($exercise));
    }

    #[Route('/exercises/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteExercise(int $id, EntityManagerInterface $em): JsonResponse
    {
        $exercise = $em->getRepository(Exercise::class)->find($id);
        if (!$exercise) {
            return $this->json(['error' => 'Exercise not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($exercise);
        $em->flush();

        return $this->json(['message' => 'Exercise deleted']);
    }

    // ---- Routines ----

    #[Route('/routines', methods: ['POST'])]
    public function createRoutine(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name']) || empty($data['goalType'])) {
            return $this->json(['error' => 'Fields "name" and "goalType" are required'], Response::HTTP_BAD_REQUEST);
        }

        $goalType = GoalType::tryFrom($data['goalType']);
        if (!$goalType) {
            return $this->json(['error' => 'Invalid goalType'], Response::HTTP_BAD_REQUEST);
        }

        $routine = new Routine();
        $routine->setName($data['name']);
        $routine->setGoalType($goalType);
        if (isset($data['description'])) {
            $routine->setDescription($data['description']);
        }
        if (isset($data['difficulty'])) {
            $difficulty = Difficulty::tryFrom($data['difficulty']);
            if (!$difficulty) {
                return $this->json(['error' => 'Invalid difficulty. Must be: beginner, intermediate, advanced'], Response::HTTP_BAD_REQUEST);
            }
            $routine->setDifficulty($difficulty);
        }

        $em->persist($routine);
        $em->flush();

        return $this->json([
            'id' => $routine->getId(),
            'name' => $routine->getName(),
            'description' => $routine->getDescription(),
            'difficulty' => $routine->getDifficulty()->value,
            'goalType' => $routine->getGoalType()?->value,
        ], Response::HTTP_CREATED);
    }

    #[Route('/routines/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateRoutine(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $routine = $em->getRepository(Routine::class)->find($id);
        if (!$routine) {
            return $this->json(['error' => 'Routine not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['name'])) {
            $routine->setName($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $routine->setDescription($data['description']);
        }
        if (isset($data['difficulty'])) {
            $difficulty = Difficulty::tryFrom($data['difficulty']);
            if (!$difficulty) {
                return $this->json(['error' => 'Invalid difficulty'], Response::HTTP_BAD_REQUEST);
            }
            $routine->setDifficulty($difficulty);
        }
        if (isset($data['goalType'])) {
            $goalType = GoalType::tryFrom($data['goalType']);
            if (!$goalType) {
                return $this->json(['error' => 'Invalid goalType'], Response::HTTP_BAD_REQUEST);
            }
            $routine->setGoalType($goalType);
        }

        $em->flush();

        return $this->json([
            'id' => $routine->getId(),
            'name' => $routine->getName(),
            'description' => $routine->getDescription(),
            'difficulty' => $routine->getDifficulty()->value,
            'goalType' => $routine->getGoalType()?->value,
        ]);
    }

    #[Route('/routines/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteRoutine(int $id, EntityManagerInterface $em): JsonResponse
    {
        $routine = $em->getRepository(Routine::class)->find($id);
        if (!$routine) {
            return $this->json(['error' => 'Routine not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($routine);
        $em->flush();

        return $this->json(['message' => 'Routine deleted']);
    }

    #[Route('/routines/{id}/exercises', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addRoutineExercise(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $routine = $em->getRepository(Routine::class)->find($id);
        if (!$routine) {
            return $this->json(['error' => 'Routine not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['exerciseId'])) {
            return $this->json(['error' => 'Field "exerciseId" is required'], Response::HTTP_BAD_REQUEST);
        }

        $exercise = $em->getRepository(Exercise::class)->find((int) $data['exerciseId']);
        if (!$exercise) {
            return $this->json(['error' => 'Exercise not found'], Response::HTTP_NOT_FOUND);
        }

        $re = new RoutineExercise();
        $re->setRoutine($routine);
        $re->setExercise($exercise);
        $re->setSets(isset($data['sets']) ? (int) $data['sets'] : null);
        $re->setReps(isset($data['reps']) ? (int) $data['reps'] : null);
        $re->setOrderIndex((int) ($data['orderIndex'] ?? $routine->getRoutineExercises()->count()));

        $em->persist($re);
        $em->flush();

        return $this->json([
            'id' => $re->getId(),
            'exerciseId' => $exercise->getId(),
            'sets' => $re->getSets(),
            'reps' => $re->getReps(),
            'orderIndex' => $re->getOrderIndex(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/routines/{id}/exercises/{reId}', methods: ['DELETE'], requirements: ['id' => '\d+', 'reId' => '\d+'])]
    public function removeRoutineExercise(int $id, int $reId, EntityManagerInterface $em): JsonResponse
    {
        $re = $em->getRepository(RoutineExercise::class)->find($reId);
        if (!$re || $re->getRoutine()?->getId() !== $id) {
            return $this->json(['error' => 'Routine exercise not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($re);
        $em->flush();

        return $this->json(['message' => 'Routine exercise deleted']);
    }

    // ---- Foods ----

    #[Route('/foods', methods: ['POST'])]
    public function createFood(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name']) || !isset($data['kcalPer100g'])) {
            return $this->json(['error' => 'Fields "name" and "kcalPer100g" are required'], Response::HTTP_BAD_REQUEST);
        }

        $food = new Food();
        $this->applyFoodFields($food, $data);

        $em->persist($food);
        $em->flush();

        return $this->json($this->serializeFood($food), Response::HTTP_CREATED);
    }

    #[Route('/foods/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateFood(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $food = $em->getRepository(Food::class)->find($id);
        if (!$food) {
            return $this->json(['error' => 'Food not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->applyFoodFields($food, $data);
        $em->flush();

        return $this->json($this->serializeFood($food));
    }

    #[Route('/foods/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteFood(int $id, EntityManagerInterface $em): JsonResponse
    {
        $food = $em->getRepository(Food::class)->find($id);
        if (!$food) {
            return $this->json(['error' => 'Food not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($food);
        $em->flush();

        return $this->json(['message' => 'Food deleted']);
    }

    // ---- Diets ----

    #[Route('/diets', methods: ['POST'])]
    public function createDiet(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name']) || empty($data['goalType'])) {
            return $this->json(['error' => 'Fields "name" and "goalType" are required'], Response::HTTP_BAD_REQUEST);
        }

        $goalType = GoalType::tryFrom($data['goalType']);
        if (!$goalType) {
            return $this->json(['error' => 'Invalid goalType'], Response::HTTP_BAD_REQUEST);
        }

        $diet = new Diet();
        $diet->setName($data['name']);
        $diet->setGoalType($goalType);
        if (isset($data['description'])) {
            $diet->setDescription($data['description']);
        }
        if (isset($data['dailyKcal'])) {
            $diet->setDailyKcal((int) $data['dailyKcal']);
        }

        $em->persist($diet);
        $em->flush();

        return $this->json([
            'id' => $diet->getId(),
            'name' => $diet->getName(),
            'description' => $diet->getDescription(),
            'dailyKcal' => $diet->getDailyKcal(),
            'goalType' => $diet->getGoalType()?->value,
        ], Response::HTTP_CREATED);
    }

    #[Route('/diets/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateDiet(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $diet = $em->getRepository(Diet::class)->find($id);
        if (!$diet) {
            return $this->json(['error' => 'Diet not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['name'])) {
            $diet->setName($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $diet->setDescription($data['description']);
        }
        if (array_key_exists('dailyKcal', $data)) {
            $diet->setDailyKcal($data['dailyKcal'] === null ? null : (int) $data['dailyKcal']);
        }
        if (isset($data['goalType'])) {
            $goalType = GoalType::tryFrom($data['goalType']);
            if (!$goalType) {
                return $this->json(['error' => 'Invalid goalType'], Response::HTTP_BAD_REQUEST);
            }
            $diet->setGoalType($goalType);
        }

        $em->flush();

        return $this->json([
            'id' => $diet->getId(),
            'name' => $diet->getName(),
            'description' => $diet->getDescription(),
            'dailyKcal' => $diet->getDailyKcal(),
            'goalType' => $diet->getGoalType()?->value,
        ]);
    }

    #[Route('/diets/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteDiet(int $id, EntityManagerInterface $em): JsonResponse
    {
        $diet = $em->getRepository(Diet::class)->find($id);
        if (!$diet) {
            return $this->json(['error' => 'Diet not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($diet);
        $em->flush();

        return $this->json(['message' => 'Diet deleted']);
    }

    #[Route('/diets/{id}/meals', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addMeal(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $diet = $em->getRepository(Diet::class)->find($id);
        if (!$diet) {
            return $this->json(['error' => 'Diet not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name'])) {
            return $this->json(['error' => 'Field "name" is required'], Response::HTTP_BAD_REQUEST);
        }

        $meal = new Meal();
        $meal->setDiet($diet);
        $meal->setName($data['name']);
        if (isset($data['mealTime'])) {
            $meal->setMealTime(new \DateTime($data['mealTime']));
        }
        if (isset($data['dayOfWeek'])) {
            $meal->setDayOfWeek((int) $data['dayOfWeek']);
        }

        $em->persist($meal);
        $em->flush();

        return $this->json([
            'id' => $meal->getId(),
            'name' => $meal->getName(),
            'mealTime' => $meal->getMealTime()?->format('H:i'),
            'dayOfWeek' => $meal->getDayOfWeek(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/meals/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteMeal(int $id, EntityManagerInterface $em): JsonResponse
    {
        $meal = $em->getRepository(Meal::class)->find($id);
        if (!$meal) {
            return $this->json(['error' => 'Meal not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($meal);
        $em->flush();

        return $this->json(['message' => 'Meal deleted']);
    }

    #[Route('/meals/{id}/foods', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addMealFood(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $meal = $em->getRepository(Meal::class)->find($id);
        if (!$meal) {
            return $this->json(['error' => 'Meal not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['foodId']) || !isset($data['quantityG'])) {
            return $this->json(['error' => 'Fields "foodId" and "quantityG" are required'], Response::HTTP_BAD_REQUEST);
        }

        $food = $em->getRepository(Food::class)->find((int) $data['foodId']);
        if (!$food) {
            return $this->json(['error' => 'Food not found'], Response::HTTP_NOT_FOUND);
        }

        $mf = new MealFood();
        $mf->setMeal($meal);
        $mf->setFood($food);
        $mf->setQuantityG((string) $data['quantityG']);

        $em->persist($mf);
        $em->flush();

        return $this->json([
            'id' => $mf->getId(),
            'foodId' => $food->getId(),
            'quantityG' => $mf->getQuantityG(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/meal-foods/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function removeMealFood(int $id, EntityManagerInterface $em): JsonResponse
    {
        $mf = $em->getRepository(MealFood::class)->find($id);
        if (!$mf) {
            return $this->json(['error' => 'Meal food not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($mf);
        $em->flush();

        return $this->json(['message' => 'Meal food deleted']);
    }

    // ---- Helpers ----

    private function applyExerciseFields(Exercise $exercise, array $data): void
    {
        if (isset($data['name'])) {
            $exercise->setName($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $exercise->setDescription($data['description']);
        }
        if (array_key_exists('muscleGroup', $data)) {
            $exercise->setMuscleGroup($data['muscleGroup']);
        }
        if (array_key_exists('equipment', $data)) {
            $exercise->setEquipment($data['equipment']);
        }
        if (array_key_exists('caloriesPerMin', $data)) {
            $exercise->setCaloriesPerMin($data['caloriesPerMin'] === null ? null : (string) $data['caloriesPerMin']);
        }
    }

    private function applyFoodFields(Food $food, array $data): void
    {
        if (isset($data['name'])) {
            $food->setName($data['name']);
        }
        if (array_key_exists('brand', $data)) {
            $food->setBrand($data['brand']);
        }
        if (isset($data['kcalPer100g'])) {
            $food->setKcalPer100g((string) $data['kcalPer100g']);
        }
        if (isset($data['proteinG'])) {
            $food->setProteinG((string) $data['proteinG']);
        }
        if (isset($data['carbsG'])) {
            $food->setCarbsG((string) $data['carbsG']);
        }
        if (isset($data['fatG'])) {
            $food->setFatG((string) $data['fatG']);
        }
        if (isset($data['fiberG'])) {
            $food->setFiberG((string) $data['fiberG']);
        }
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'role' => $user->getRole()->value,
            'goalType' => $user->getGoalType()?->value,
            'assignedRoutineId' => $user->getAssignedRoutine()?->getId(),
            'assignedDietId' => $user->getAssignedDiet()?->getId(),
            'createdAt' => $user->getCreatedAt()->format('c'),
        ];
    }

    private function serializeExercise(Exercise $exercise): array
    {
        return [
            'id' => $exercise->getId(),
            'name' => $exercise->getName(),
            'description' => $exercise->getDescription(),
            'muscleGroup' => $exercise->getMuscleGroup(),
            'equipment' => $exercise->getEquipment(),
            'caloriesPerMin' => $exercise->getCaloriesPerMin(),
        ];
    }

    private function serializeFood(Food $food): array
    {
        return [
            'id' => $food->getId(),
            'name' => $food->getName(),
            'brand' => $food->getBrand(),
            'kcalPer100g' => $food->getKcalPer100g(),
            'proteinG' => $food->getProteinG(),
            'carbsG' => $food->getCarbsG(),
            'fatG' => $food->getFatG(),
            'fiberG' => $food->getFiberG(),
        ];
    }
}
