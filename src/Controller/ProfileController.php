<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('profile/index.html.twig', [
            'profile_user' => $user,
            'pages_count' => $user->getPages()->count(),
            'launches_count' => $user->getLaunches()->count(),
            'active_panel' => (string) $request->query->get('panel', ''),
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['POST'])]
    public function edit(Request $request, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_edit', (string) $request->request->get('_token'))) {
            $this->addFlash('warning', 'Session invalide, merci de reessayer.');

            return $this->redirectToRoute('app_profile', ['panel' => 'edit']);
        }

        $email = trim((string) $request->request->get('email', ''));
        $username = trim((string) $request->request->get('username', ''));

        if ($email === '' || $username === '') {
            $this->addFlash('warning', 'Email et pseudo sont obligatoires.');

            return $this->redirectToRoute('app_profile', ['panel' => 'edit']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('warning', 'Email invalide.');

            return $this->redirectToRoute('app_profile', ['panel' => 'edit']);
        }

        $existingByEmail = $userRepository->findOneBy(['email' => strtolower($email)]);
        if ($existingByEmail instanceof User && $existingByEmail->getId() !== $user->getId()) {
            $this->addFlash('warning', 'Cet email est deja utilise.');

            return $this->redirectToRoute('app_profile', ['panel' => 'edit']);
        }

        $existingByUsername = $userRepository->findOneBy(['username' => $username]);
        if ($existingByUsername instanceof User && $existingByUsername->getId() !== $user->getId()) {
            $this->addFlash('warning', 'Ce pseudo est deja utilise.');

            return $this->redirectToRoute('app_profile', ['panel' => 'edit']);
        }

        $user->setEmail(strtolower($email));
        $user->setUsername($username);
        $this->entityManager->flush();

        $this->addFlash('success', 'Profil mis a jour.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/photo', name: 'app_profile_photo', methods: ['POST'])]
    public function updatePhoto(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_photo', (string) $request->request->get('_token'))) {
            $this->addFlash('warning', 'Session invalide, merci de reessayer.');

            return $this->redirectToRoute('app_profile');
        }

        $photo = $request->files->get('profile_photo');
        if (!$photo instanceof UploadedFile) {
            $this->addFlash('warning', 'Aucun fichier selectionne.');

            return $this->redirectToRoute('app_profile');
        }

        if ($photo->getError() !== \UPLOAD_ERR_OK) {
            $this->addFlash('warning', 'Le fichier na pas pu etre televerse.');

            return $this->redirectToRoute('app_profile');
        }

        if ($photo->getSize() > 5 * 1024 * 1024) {
            $this->addFlash('warning', 'La taille maximale autorisee est de 5 Mo.');

            return $this->redirectToRoute('app_profile');
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($photo->getMimeType(), $allowedMimeTypes, true)) {
            $this->addFlash('warning', 'Format invalide. Utilise JPG, PNG, GIF ou WebP.');

            return $this->redirectToRoute('app_profile');
        }

        $user->setImageFile($photo);
        $this->entityManager->flush();

        $this->addFlash('success', 'Photo de profil mise a jour.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/change-password', name: 'app_profile_change_password', methods: ['POST'])]
    public function changePassword(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_change_password', (string) $request->request->get('_token'))) {
            $this->addFlash('warning', 'Session invalide, merci de reessayer.');

            return $this->redirectToRoute('app_profile', ['panel' => 'password']);
        }

        $currentPassword = (string) $request->request->get('current_password', '');
        $newPassword = (string) $request->request->get('new_password', '');
        $confirmPassword = (string) $request->request->get('confirm_password', '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $this->addFlash('warning', 'Tous les champs mot de passe sont obligatoires.');

            return $this->redirectToRoute('app_profile', ['panel' => 'password']);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('warning', 'Le mot de passe actuel est incorrect.');

            return $this->redirectToRoute('app_profile', ['panel' => 'password']);
        }

        if (strlen($newPassword) < 8) {
            $this->addFlash('warning', 'Le nouveau mot de passe doit contenir au moins 8 caracteres.');

            return $this->redirectToRoute('app_profile', ['panel' => 'password']);
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('warning', 'La confirmation du mot de passe ne correspond pas.');

            return $this->redirectToRoute('app_profile', ['panel' => 'password']);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();

        $this->addFlash('success', 'Mot de passe mis a jour.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteAccount(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_delete', (string) $request->request->get('_token'))) {
            $this->addFlash('warning', 'Requete invalide.');

            return $this->redirectToRoute('app_profile');
        }

        foreach ($user->getLaunches()->toArray() as $launch) {
            $this->entityManager->remove($launch);
        }

        foreach ($user->getPages()->toArray() as $page) {
            $this->entityManager->remove($page);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        return $this->redirectToRoute('app_login');
    }
}
