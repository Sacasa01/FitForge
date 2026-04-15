<?php

namespace App\Controller;

use App\Entity\Diet;
use App\Entity\Meal;
use App\Entity\MealFood;
use App\Entity\User;
use App\Enum\GoalType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DietController extends AbstractController
{
    #[Route('/api/diets', methods: ['GET'])]
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

        $diets = $em->getRepository(Diet::class)->findBy($criteria, ['id' => 'ASC']);

        $data = array_map(fn(Diet $d) => [
            'id' => $d->getId(),
            'name' => $d->getName(),
            'description' => $d->getDescription(),
            'dailyKcal' => $d->getDailyKcal(),
            'goalType' => $d->getGoalType()?->value,
            'mealCount' => $d->getMeals()->count(),
        ], $diets);

        return $this->json(['data' => $data]);
    }

    #[Route('/api/diets/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id, EntityManagerInterface $em): JsonResponse
    {
        $diet = $em->getRepository(Diet::class)->find($id);
        if (!$diet) {
            return $this->json(['error' => 'Diet not found'], Response::HTTP_NOT_FOUND);
        }

        $meals = array_map(fn(Meal $m) => [
            'id' => $m->getId(),
            'name' => $m->getName(),
            'mealTime' => $m->getMealTime()?->format('H:i'),
            'dayOfWeek' => $m->getDayOfWeek(),
            'foods' => array_map(fn(MealFood $mf) => [
                'id' => $mf->getId(),
                'food' => [
                    'id' => $mf->getFood()->getId(),
                    'name' => $mf->getFood()->getName(),
                    'brand' => $mf->getFood()->getBrand(),
                    'kcalPer100g' => $mf->getFood()->getKcalPer100g(),
                ],
                'quantityG' => $mf->getQuantityG(),
            ], $m->getMealFoods()->toArray()),
        ], $diet->getMeals()->toArray());

        return $this->json([
            'id' => $diet->getId(),
            'name' => $diet->getName(),
            'description' => $diet->getDescription(),
            'dailyKcal' => $diet->getDailyKcal(),
            'goalType' => $diet->getGoalType()?->value,
            'meals' => $meals,
        ]);
    }

    #[Route('/api/users/me/assign-diet', methods: ['POST'])]
    public function assign(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['dietId'])) {
            return $this->json(['error' => 'Field "dietId" is required'], Response::HTTP_BAD_REQUEST);
        }

        $diet = $em->getRepository(Diet::class)->find((int) $data['dietId']);
        if (!$diet) {
            return $this->json(['error' => 'Diet not found'], Response::HTTP_NOT_FOUND);
        }

        $user->setAssignedDiet($diet);
        $em->flush();

        return $this->json([
            'message' => 'Diet assigned successfully',
            'assignedDietId' => $diet->getId(),
        ]);
    }
}
