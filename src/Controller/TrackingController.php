<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\LaunchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TrackingController extends AbstractController
{
    #[Route('/suivi-personnel', name: 'app_personal_tracking', methods: ['GET'])]
    public function index(LaunchRepository $launchRepository): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $today = new \DateTimeImmutable();
        $dailyGoalTarget = 1;
        $dailyGoalDone = $launchRepository->countDailySessionsForUser($user, $today);
        $dailyGoalPercent = min(100, (int) round(($dailyGoalDone / $dailyGoalTarget) * 100));

        $totalSessions = $launchRepository->countTotalSessionsForUser($user);
        $streakDays = $launchRepository->countCurrentStreakDaysForUser($user, $today);
        $totalDurationSeconds = $launchRepository->sumTotalDurationSecondsForUser($user);

        $weeklySeriesRaw = $launchRepository->findDailyCountsForLastDays($user, 7, $today);
        $weeklySeries = [];
        $weekTotalSessions = 0;
        $weekMax = 1;
        foreach ($weeklySeriesRaw as $point) {
            $date = $point['date'];
            $count = $point['count'];
            $weekTotalSessions += $count;
            $weekMax = max($weekMax, $count);

            $weeklySeries[] = [
                'label' => self::dayLabel($date),
                'count' => $count,
            ];
        }

        $milestones = [
            [
                'label' => '1ere session',
                'target' => 1,
                'progress' => $totalSessions,
                'unlocked' => $totalSessions >= 1,
            ],
            [
                'label' => '7 jours de suite',
                'target' => 7,
                'progress' => $streakDays,
                'unlocked' => $streakDays >= 7,
            ],
            [
                'label' => '30 sessions',
                'target' => 30,
                'progress' => $totalSessions,
                'unlocked' => $totalSessions >= 30,
            ],
        ];

        return $this->render('tracking/index.html.twig', [
            'daily_goal_target' => $dailyGoalTarget,
            'daily_goal_done' => $dailyGoalDone,
            'daily_goal_percent' => $dailyGoalPercent,
            'total_sessions' => $totalSessions,
            'week_total_sessions' => $weekTotalSessions,
            'streak_days' => $streakDays,
            'total_duration_label' => self::formatDurationLabel($totalDurationSeconds),
            'weekly_series' => $weeklySeries,
            'weekly_max' => $weekMax,
            'recent_launches' => $launchRepository->findRecentForUser($user, 10),
            'milestones' => $milestones,
        ]);
    }

    private static function formatDurationLabel(int $totalSeconds): string
    {
        if ($totalSeconds <= 0) {
            return '0 min';
        }

        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);

        if ($hours > 0) {
            return $hours . ' h ' . $minutes . ' min';
        }

        return max(1, $minutes) . ' min';
    }

    private static function dayLabel(\DateTimeImmutable $date): string
    {
        $labels = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mer',
            4 => 'Jeu',
            5 => 'Ven',
            6 => 'Sam',
            7 => 'Dim',
        ];

        return $labels[(int) $date->format('N')] ?? $date->format('d/m');
    }
}
