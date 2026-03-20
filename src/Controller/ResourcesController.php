<?php

namespace App\Controller;

use App\Repository\MenuRepository;
use App\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResourcesController extends AbstractController
{
    #[Route('/ressources-bien-etre', name: 'app_resources_wellbeing', methods: ['GET'])]
    public function index(
        Request $request,
        PageRepository $pageRepository,
        MenuRepository $menuRepository
    ): Response {
        $search = trim((string) $request->query->get('q', ''));
        $menuRaw = trim((string) $request->query->get('menu', ''));
        $selectedMenuId = ctype_digit($menuRaw) ? (int) $menuRaw : null;

        $resources = $pageRepository->findPublishedWithFilters(
            $search !== '' ? $search : null,
            $selectedMenuId,
            24
        );

        $featured = $resources[0] ?? null;
        $otherResources = $featured ? array_slice($resources, 1) : [];

        $menuChoices = [];
        foreach ($menuRepository->findActiveWithPublishedPages() as $menu) {
            if ($menu->getPages()->count() === 0) {
                continue;
            }

            $menuChoices[] = $menu;
        }

        return $this->render('resources/index.html.twig', [
            'featured_resource' => $featured,
            'resources' => $otherResources,
            'search' => $search,
            'selected_menu_id' => $selectedMenuId,
            'menu_choices' => $menuChoices,
        ]);
    }
}

