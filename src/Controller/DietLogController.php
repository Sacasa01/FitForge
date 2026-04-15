<?php

namespace App\Controller;

use App\Entity\Diet;
use App\Entity\DietLog;
use App\Entity\Meal;
use App\Entity\MealLog;
use App\Entity\User;
use App\Enum\GeneralFeeling;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/diet-logs')]
class DietLogController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $log = new DietLog();
        $log->setUser($user);

        if (isset($data['loggedAt'])) {
            $log->setLoggedAt(new \DateTime($data['loggedAt']));
        }

        if (!empty($data['dietId'])) {
            $diet = $em->getRepository(Diet::class)->find((int) $data['dietId']);
            if (!$diet) {
                return $this->json(['error' => 'Diet not found'], Response::HTTP_NOT_FOUND);
            }
            $log->setDiet($diet);
        } elseif ($user->getAssignedDiet()) {
            $log->setDiet($user->getAssignedDiet());
        }

        if (isset($data['generalFeeling'])) {
            $feeling = GeneralFeeling::tryFrom($data['generalFeeling']);
            if (!$feeling) {
                return $this->json([
                    'error' => 'Invalid generalFeeling. Must be one of: bad, regular, good, great',
                ], Response::HTTP_BAD_REQUEST);
            }
            $log->setGeneralFeeling($feeling);
        }

        if (isset($data['notes'])) {
            $log->setNotes($data['notes']);
        }

        $em->persist($log);
        $em->flush();

        return $this->json([
            'id' => $log->getId(),
            'loggedAt' => $log->getLoggedAt()->format('Y-m-d'),
            'dietId' => $log->getDiet()?->getId(),
            'generalFeeling' => $log->getGeneralFeeling()?->value,
            'notes' => $log->getNotes(),
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

        $repo = $em->getRepository(DietLog::class);
        $total = $repo->count(['user' => $user]);

        $logs = $repo->findBy(
            ['user' => $user],
            ['loggedAt' => 'DESC'],
            $limit,
            $offset,
        );

        $items = array_map(fn(DietLog $l) => [
            'id' => $l->getId(),
            'loggedAt' => $l->getLoggedAt()->format('Y-m-d'),
            'dietId' => $l->getDiet()?->getId(),
            'generalFeeling' => $l->getGeneralFeeling()?->value,
            'notes' => $l->getNotes(),
            'mealCount' => $l->getMealLogs()->count(),
        ], $logs);

        return $this->json([
            'data' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    #[Route('/{id}/meals', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markMeal(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $log = $this->findLogForCurrentUser($id, $em);
        if ($log instanceof JsonResponse) {
            return $log;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['mealId'])) {
            return $this->json(['error' => 'Field "mealId" is required'], Response::HTTP_BAD_REQUEST);
        }

        $meal = $em->getRepository(Meal::class)->find((int) $data['mealId']);
        if (!$meal) {
            return $this->json(['error' => 'Meal not found'], Response::HTTP_NOT_FOUND);
        }

        $mealLog = new MealLog();
        $mealLog->setDietLog($log);
        $mealLog->setMeal($meal);

        $em->persist($mealLog);
        $em->flush();

        return $this->json([
            'id' => $mealLog->getId(),
            'mealId' => $meal->getId(),
            'mealName' => $meal->getName(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/meals/{mealId}', methods: ['PUT'], requirements: ['id' => '\d+', 'mealId' => '\d+'])]
    public function rateMeal(int $id, int $mealId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $log = $this->findLogForCurrentUser($id, $em);
        if ($log instanceof JsonResponse) {
            return $log;
        }

        $mealLog = $em->getRepository(MealLog::class)->find($mealId);
        if (!$mealLog || $mealLog->getDietLog()?->getId() !== $log->getId()) {
            return $this->json(['error' => 'Meal log not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['enjoyment'])) {
            $enjoyment = (int) $data['enjoyment'];
            if ($enjoyment < 1 || $enjoyment > 5) {
                return $this->json(['error' => 'Enjoyment must be between 1 and 5'], Response::HTTP_BAD_REQUEST);
            }
            $mealLog->setEnjoyment($enjoyment);
        }

        if (isset($data['satiety'])) {
            $satiety = (int) $data['satiety'];
            if ($satiety < 1 || $satiety > 5) {
                return $this->json(['error' => 'Satiety must be between 1 and 5'], Response::HTTP_BAD_REQUEST);
            }
            $mealLog->setSatiety($satiety);
        }

        $em->flush();

        return $this->json([
            'id' => $mealLog->getId(),
            'enjoyment' => $mealLog->getEnjoyment(),
            'satiety' => $mealLog->getSatiety(),
        ]);
    }

    private function findLogForCurrentUser(int $id, EntityManagerInterface $em): DietLog|JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $log = $em->getRepository(DietLog::class)->find($id);
        if (!$log) {
            return $this->json(['error' => 'Diet log not found'], Response::HTTP_NOT_FOUND);
        }
        if ($log->getUser()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $log;
    }
}
