<?php

namespace App\Controller\Admin;

use App\Entity\BreathingExercise;
use App\Entity\Launch;
use App\Entity\Menu;
use App\Entity\Page;
use App\Entity\User;
use App\Repository\BreathingExerciseRepository;
use App\Repository\LaunchRepository;
use App\Repository\MenuRepository;
use App\Repository\PageRepository;
use App\Repository\UserRepository;
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
        private readonly UserRepository $userRepository,
        private readonly PageRepository $pageRepository,
        private readonly MenuRepository $menuRepository,
        private readonly BreathingExerciseRepository $breathingExerciseRepository,
        private readonly LaunchRepository $launchRepository,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users' => $this->userRepository->count([]),
                'pages' => $this->pageRepository->count([]),
                'menus' => $this->menuRepository->count([]),
                'exercises' => $this->breathingExerciseRepository->count([]),
                'launches' => $this->launchRepository->count([]),
            ],
            'quickLinks' => [
                [
                    'label' => 'Gerer les utilisateurs',
                    'url' => $this->generateUrl('admin_user_index'),
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
