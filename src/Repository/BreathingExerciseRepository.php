<?php

namespace App\Repository;

use App\Entity\BreathingExercise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BreathingExercise>
 */
class BreathingExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BreathingExercise::class);
    }

    /**
     * @return BreathingExercise[]
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.active = :active')
            ->setParameter('active', true)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
