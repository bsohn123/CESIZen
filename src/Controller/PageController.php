<?php

namespace App\Controller;

use App\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route('/ressources/{slug}', name: 'app_page_show', methods: ['GET'])]
    public function show(string $slug, PageRepository $pageRepository): Response
    {
        $page = $pageRepository->findPublishedBySlug($slug);

        if (!$page) {
            throw $this->createNotFoundException('Ressource introuvable.');
        }

        return $this->render('page/show.html.twig', [
            'page' => $page,
        ]);
    }
}
