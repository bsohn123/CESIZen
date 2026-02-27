<?php

namespace App\Controller;

use App\Repository\MenuRepository;
use App\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(MenuRepository $menuRepository, PageRepository $pageRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'menus' => $menuRepository->findActiveWithPublishedPages(),
            'resources' => $pageRepository->findLatestPublished(2),
        ]);
    }
}
