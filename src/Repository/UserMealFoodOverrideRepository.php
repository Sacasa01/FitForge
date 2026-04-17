<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserMealFoodOverride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserMealFoodOverride>
 */
class UserMealFoodOverrideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserMealFoodOverride::class);
    }

    /**
     * @param int[] $mealFoodIds
     * @return array<int, float> mealFoodId => quantityG
     */
    public function loadOverridesMap(User $user, array $mealFoodIds): array
    {
        if (empty($mealFoodIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('o')
            ->select('IDENTITY(o.mealFood) AS mealFoodId', 'o.quantityG AS quantityG')
            ->where('o.user = :user')
            ->andWhere('o.mealFood IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $mealFoodIds)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['mealFoodId']] = (float) $row['quantityG'];
        }

        return $map;
    }
}
