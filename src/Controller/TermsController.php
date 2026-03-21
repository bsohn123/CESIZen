<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TermsController extends AbstractController
{
    #[Route('/conditions-utilisation', name: 'app_terms', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('security/terms.html.twig');
    }

    #[Route('/mentions-legales', name: 'app_legal_mentions', methods: ['GET'])]
    public function mentions(): Response
    {
        return $this->render('legal/mentions.html.twig');
    }

    #[Route('/politique-de-confidentialite', name: 'app_legal_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    #[Route('/gestion-des-donnees-personnelles', name: 'app_legal_data', methods: ['GET'])]
    public function data(): Response
    {
        return $this->render('legal/data.html.twig');
    }
}
