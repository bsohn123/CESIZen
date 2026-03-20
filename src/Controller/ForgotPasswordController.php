<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForgotPasswordController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));

            $user = $userRepository->findByEmail($email);

            if ($user !== null && $user->isActive()) {
                $token = bin2hex(random_bytes(32));
                $user->setResetToken($token);
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                $em->flush();

                $resetUrl = $this->generateUrl(
                    'app_reset_password',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $emailMessage = (new Email())
                    ->from('noreply@cesizen.fr')
                    ->to($user->getEmail())
                    ->subject('Reinitialisation de votre mot de passe — CESIZen')
                    ->html($this->renderView('email/reset_password.html.twig', [
                        'username' => $user->getUsername() ?? $user->getEmail(),
                        'resetUrl' => $resetUrl,
                    ]));

                $mailer->send($emailMessage);
            }

            // Always show the same message to avoid user enumeration
            $this->addFlash('success', 'Si un compte existe avec cet email, un lien de reinitialisation a ete envoye.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        return $this->render('security/forgot_password_request.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user = $userRepository->findByResetToken($token);

        if ($user === null) {
            $this->addFlash('error', 'Ce lien est invalide ou a expire. Veuillez refaire une demande.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password', '');
            $confirm  = (string) $request->request->get('password_confirm', '');

            $errors = [];

            if (strlen($password) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caracteres.';
            }

            if ($password !== $confirm) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            if ($errors === []) {
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $em->flush();

                $this->addFlash('success', 'Mot de passe reinitialise avec succes. Tu peux te connecter.');

                return $this->redirectToRoute('app_login');
            }

            return $this->render('security/reset_password.html.twig', [
                'token'  => $token,
                'errors' => $errors,
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('security/reset_password.html.twig', [
            'token'  => $token,
            'errors' => [],
        ]);
    }
}
