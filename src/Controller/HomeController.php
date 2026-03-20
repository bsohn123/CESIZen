<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\LaunchRepository;
use App\Repository\MenuRepository;
use App\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        MenuRepository $menuRepository,
        PageRepository $pageRepository,
        LaunchRepository $launchRepository
    ): Response
    {
        $dailyGoalTarget = 1;
        $dailyGoalDone = 0;
        $streakDays = 0;
        $totalSessions = 0;

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser instanceof User) {
            $dailyGoalDone = $launchRepository->countDailySessionsForUser($currentUser, new \DateTimeImmutable());
            $streakDays = $launchRepository->countCurrentStreakDaysForUser($currentUser, new \DateTimeImmutable());
            $totalSessions = $launchRepository->countTotalSessionsForUser($currentUser);
        }

        $dailyGoalPercent = min(100, (int) round(($dailyGoalDone / $dailyGoalTarget) * 100));
        $milestones = [
            [
                'label' => '1ère session',
                'target' => 1,
                'progress' => $totalSessions,
                'unlocked' => $totalSessions >= 1,
            ],
            [
                'label' => '7 jours',
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

        return $this->render('home/index.html.twig', [
            'menus' => $menuRepository->findActiveWithPublishedPages(),
            'resources' => $pageRepository->findLatestPublished(2),
            'daily_goal_done' => $dailyGoalDone,
            'daily_goal_target' => $dailyGoalTarget,
            'daily_goal_percent' => $dailyGoalPercent,
            'milestones' => $milestones,
        ]);
    }
}
