<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ForgotPasswordController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Placeholder behavior for now: do not reveal whether the email exists.
            $this->addFlash('success', 'Si un compte existe avec cet email, un lien de reinitialisation sera envoye.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        return $this->render('security/forgot_password_request.html.twig');
    }
}
