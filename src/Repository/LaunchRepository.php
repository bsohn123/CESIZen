<?php

namespace App\Repository;

use App\Entity\Launch;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Launch>
 */
class LaunchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Launch::class);
    }

    public function countDailySessionsForUser(User $user, \DateTimeImmutable $day): int
    {
        $start = $day->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.user = :user')
            ->andWhere('l.launchDate >= :start')
            ->andWhere('l.launchDate < :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTotalSessionsForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countCurrentStreakDaysForUser(User $user, \DateTimeImmutable $day): int
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.launchDate')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.launchDate', 'DESC')
            ->getQuery()
            ->getResult();

        if ($rows === []) {
            return 0;
        }

        $activeDays = [];
        foreach ($rows as $row) {
            $launchDate = $row['launchDate'] ?? null;
            if ($launchDate instanceof \DateTimeImmutable) {
                $activeDays[$launchDate->format('Y-m-d')] = true;
            }
        }

        $cursor = $day->setTime(0, 0, 0);
        $streak = 0;

        while (isset($activeDays[$cursor->format('Y-m-d')])) {
            $streak++;
            $cursor = $cursor->modify('-1 day');
        }

        return $streak;
    }

    /**
     * @return array<int, array{date:\DateTimeImmutable, count:int}>
     */
    public function findDailyCountsForLastDays(User $user, int $days, \DateTimeImmutable $day): array
    {
        $days = max(1, $days);
        $todayStart = $day->setTime(0, 0, 0);
        $start = $todayStart->modify('-' . ($days - 1) . ' days');
        $end = $todayStart->modify('+1 day');

        $rows = $this->createQueryBuilder('l')
            ->select('l.launchDate')
            ->andWhere('l.user = :user')
            ->andWhere('l.launchDate >= :start')
            ->andWhere('l.launchDate < :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $countsByDay = [];
        foreach ($rows as $row) {
            $launchDate = $row['launchDate'] ?? null;
            if ($launchDate instanceof \DateTimeImmutable) {
                $key = $launchDate->format('Y-m-d');
                $countsByDay[$key] = ($countsByDay[$key] ?? 0) + 1;
            }
        }

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $todayStart->modify('-' . $i . ' days');
            $key = $date->format('Y-m-d');
            $series[] = [
                'date' => $date,
                'count' => (int) ($countsByDay[$key] ?? 0),
            ];
        }

        return $series;
    }

    public function sumTotalDurationSecondsForUser(User $user): int
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.totalDuration')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $seconds = 0;
        foreach ($rows as $row) {
            $duration = $row['totalDuration'] ?? null;
            if ($duration instanceof \DateTimeImmutable) {
                $hours = (int) $duration->format('H');
                $minutes = (int) $duration->format('i');
                $secs = (int) $duration->format('s');
                $seconds += ($hours * 3600) + ($minutes * 60) + $secs;
            }
        }

        return $seconds;
    }

    /**
     * @return Launch[]
     */
    public function findRecentForUser(User $user, int $limit = 8): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.breathingExercise', 'e')
            ->addSelect('e')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.launchDate', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function countBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.launchDate >= :start')
            ->andWhere('l.launchDate < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveUsersBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(DISTINCT u.id)')
            ->join('l.user', 'u')
            ->andWhere('l.launchDate >= :start')
            ->andWhere('l.launchDate < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, array{date:\DateTimeImmutable, count:int}>
     */
    public function findGlobalDailyCountsForLastDays(int $days, \DateTimeImmutable $day): array
    {
        $days = max(1, $days);
        $todayStart = $day->setTime(0, 0, 0);
        $start = $todayStart->modify('-' . ($days - 1) . ' days');
        $end = $todayStart->modify('+1 day');

        $rows = $this->createQueryBuilder('l')
            ->select('l.launchDate')
            ->andWhere('l.launchDate >= :start')
            ->andWhere('l.launchDate < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $countsByDay = [];
        foreach ($rows as $row) {
            $launchDate = $row['launchDate'] ?? null;
            if ($launchDate instanceof \DateTimeImmutable) {
                $key = $launchDate->format('Y-m-d');
                $countsByDay[$key] = ($countsByDay[$key] ?? 0) + 1;
            }
        }

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $todayStart->modify('-' . $i . ' days');
            $key = $date->format('Y-m-d');
            $series[] = [
                'date' => $date,
                'count' => (int) ($countsByDay[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array{name:string, launches:int}>
     */
    public function findTopExercisesForPeriod(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        int $limit = 5
    ): array {
        $rows = $this->createQueryBuilder('l')
            ->select('e.name AS name, COUNT(l.id) AS launches')
            ->join('l.breathingExercise', 'e')
            ->andWhere('l.launchDate >= :start')
            ->andWhere('l.launchDate < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('e.id')
            ->orderBy('launches', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): array => [
                'name' => (string) ($row['name'] ?? 'Exercice'),
                'launches' => (int) ($row['launches'] ?? 0),
            ],
            $rows
        );
    }
}
