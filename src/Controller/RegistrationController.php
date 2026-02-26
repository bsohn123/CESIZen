<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        $email = '';
        $username = '';
        $acceptTerms = false;
        $errors = [];

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $username = trim((string) $request->request->get('username', ''));
            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');
            $acceptTerms = '1' === (string) $request->request->get('accept_terms', '0');
            $csrfToken = (string) $request->request->get('_csrf_token', '');

            if (!$this->isCsrfTokenValid('register', $csrfToken)) {
                $errors[] = 'Session invalide. Recharge la page et réessaie.';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email invalide.';
            }

            if (strlen($username) < 3) {
                $errors[] = 'Le pseudo doit contenir au moins 3 caractères.';
            }

            if (strlen($password) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }

            if ($password !== $passwordConfirm) {
                $errors[] = 'La confirmation du mot de passe ne correspond pas.';
            }

            if (!$acceptTerms) {
                $errors[] = "Tu dois accepter les conditions d'utilisation.";
            }

            if ($userRepository->findOneBy(['email' => $email])) {
                $errors[] = 'Un compte existe déjà avec cet email.';
            }

            if ($userRepository->findOneBy(['username' => $username])) {
                $errors[] = 'Ce pseudo est déjà utilisé.';
            }

            if (!$errors) {
                $user = new User();
                $user->setEmail(strtolower($email));
                $user->setUsername($username);
                $user->setRoles(['ROLE_USER']);
                $user->setActive(true);
                $user->setPassword($passwordHasher->hashPassword($user, $password));

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Compte créé. Tu peux maintenant te connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'register_errors' => $errors,
            'register_email' => $email,
            'register_username' => $username,
            'register_accept_terms' => $acceptTerms,
        ]);
    }
}
