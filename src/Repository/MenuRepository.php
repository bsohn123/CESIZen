<?php

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    public function findActiveWithPublishedPages(): array
    {
        $publishedStatuses = ['publiee', 'publie', 'published'];

        return $this->createQueryBuilder('m')
            ->leftJoin('m.pages', 'p', 'WITH', 'LOWER(p.status) IN (:statuses)')
            ->addSelect('p')
            ->where('m.active = :active')
            ->setParameter('active', true)
            ->setParameter('statuses', $publishedStatuses)
            ->orderBy('m.displayOrder', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
