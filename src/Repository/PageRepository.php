<?php

namespace App\Repository;

use App\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Page>
 */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    public function findLatestPublished(int $limit = 2): array
    {
        $publishedStatuses = ['publiee', 'publie', 'published'];

        return $this->createQueryBuilder('p')
            ->andWhere('LOWER(p.status) IN (:statuses)')
            ->setParameter('statuses', $publishedStatuses)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPublishedBySlug(string $slug): ?Page
    {
        $publishedStatuses = ['publiee', 'publie', 'published'];

        return $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->andWhere('LOWER(p.status) IN (:statuses)')
            ->setParameter('slug', $slug)
            ->setParameter('statuses', $publishedStatuses)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
