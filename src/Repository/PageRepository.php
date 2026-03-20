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

    /**
     * @return Page[]
     */
    public function findPublishedWithFilters(?string $search = null, ?int $menuId = null, int $limit = 24): array
    {
        $publishedStatuses = ['publiee', 'publie', 'published'];

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.menu', 'm')
            ->addSelect('m')
            ->andWhere('LOWER(p.status) IN (:statuses)')
            ->setParameter('statuses', $publishedStatuses)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($menuId !== null && $menuId > 0) {
            $qb->andWhere('IDENTITY(p.menu) = :menuId')
                ->setParameter('menuId', $menuId);
        }

        $needle = trim((string) $search);
        if ($needle !== '') {
            $needle = strtolower($needle);
            $qb->andWhere('LOWER(p.title) LIKE :q OR LOWER(p.content) LIKE :q')
                ->setParameter('q', '%' . $needle . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countPublished(): int
    {
        $publishedStatuses = ['publiee', 'publie', 'published'];

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('LOWER(p.status) IN (:statuses)')
            ->setParameter('statuses', $publishedStatuses)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param string[] $statuses
     * @return Page[]
     */
    public function findLatestByNormalizedStatuses(array $statuses, int $limit = 5): array
    {
        $normalized = array_values(array_unique(array_map('strtolower', $statuses)));
        if ($normalized === []) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->leftJoin('p.menu', 'm')
            ->addSelect('m')
            ->andWhere('LOWER(p.status) IN (:statuses)')
            ->setParameter('statuses', $normalized)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }
}
