<?php

namespace App\Controller\Admin;

use App\Entity\BreathingExercise;
use App\Entity\Launch;
use App\Entity\Menu;
use App\Entity\Page;
use App\Entity\User;
use App\Repository\LaunchRepository;
use App\Repository\PageRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\ColorScheme;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly LaunchRepository $launchRepository,
    ) {
    }

    public function index(): Response
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $last7Start = $today->modify('-6 days');

        $sessionsToday = $this->launchRepository->countBetween($today, $tomorrow);
        $sessionsLast7 = $this->launchRepository->countBetween($last7Start, $tomorrow);
        $activeUsersLast7 = $this->launchRepository->countActiveUsersBetween($last7Start, $tomorrow);
        $publishedPages = $this->pageRepository->countPublished();

        $dailySeriesRaw = $this->launchRepository->findGlobalDailyCountsForLastDays(30, new \DateTimeImmutable());
        $dailySeries = [];
        $dailyMax = 1;
        foreach ($dailySeriesRaw as $point) {
            $date = $point['date'];
            $count = $point['count'];
            $dailyMax = max($dailyMax, $count);

            $dailySeries[] = [
                'label' => $date->format('d/m'),
                'count' => $count,
            ];
        }

        $topExercises = $this->launchRepository->findTopExercisesForPeriod($last7Start, $tomorrow, 5);
        $topExercisesMax = 1;
        foreach ($topExercises as $exercise) {
            $topExercisesMax = max($topExercisesMax, (int) $exercise['launches']);
        }

        $draftPages = $this->pageRepository->findLatestByNormalizedStatuses(['brouillon', 'draft'], 5);
        $archivedPages = $this->pageRepository->findLatestByNormalizedStatuses(['archivee', 'archive', 'archived'], 5);

        return $this->render('admin/dashboard.html.twig', [
            'kpis' => [
                'sessions_today' => $sessionsToday,
                'sessions_last7' => $sessionsLast7,
                'active_users_last7' => $activeUsersLast7,
                'published_pages' => $publishedPages,
            ],
            'charts' => [
                'daily_series' => $dailySeries,
                'daily_max' => $dailyMax,
                'top_exercises' => $topExercises,
                'top_exercises_max' => $topExercisesMax,
            ],
            'todo' => [
                'draft_pages' => $draftPages,
                'archived_pages' => $archivedPages,
            ],
            'quick_links' => [
                [
                    'label' => 'Gerer les utilisateurs',
                    'url' => $this->generateUrl('admin_user_index'),
                ],
                [
                    'label' => 'Gerer les pages',
                    'url' => $this->generateUrl('admin_page_index'),
                ],
                [
                    'label' => 'Gerer les exercices',
                    'url' => $this->generateUrl('admin_breathing_exercise_index'),
                ],
            ],
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('CESIZen')
            ->disableDarkMode()
            ->setDefaultColorScheme(ColorScheme::LIGHT);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('styles/admin-nature.css');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Utilisateur', 'fas fa-user', User::class);
        yield MenuItem::linkToCrud('Page', 'fas fa-file-alt', Page::class);
        yield MenuItem::linkToCrud('Menu', 'fas fa-bars', Menu::class);
        yield MenuItem::linkToCrud('Exercice de respiration', 'fas fa-heartbeat', BreathingExercise::class);
        yield MenuItem::linkToCrud('Lancement', 'fas fa-play-circle', Launch::class);
        yield MenuItem::linkToRoute('Quitter', 'fas fa-right-from-bracket', 'app_home');
    }
}
