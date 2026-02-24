<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email', 'Email');
        yield TextField::new('username', 'Pseudo');
        yield ChoiceField::new('roles', 'Roles')
            ->setChoices([
                'Utilisateur' => 'ROLE_USER',
                'Admin' => 'ROLE_ADMIN',
            ])
            ->allowMultipleChoices();
        yield BooleanField::new('active', 'Actif');
        yield DateTimeField::new('createdAt', 'Cree le')->hideOnForm();
        yield DateTimeField::new('lastLoginAt', 'Derniere connexion')->hideOnForm();
        yield TextField::new('plainPassword', 'Mot de passe')
            ->onlyOnForms()
            ->setRequired('new' === $pageName)
            ->setFormType(PasswordType::class)
            ->setFormTypeOption('mapped', false);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->hashPlainPassword($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->hashPlainPassword($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPlainPassword(User $user): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $formData = $request->request->all('User');
        $plainPassword = $formData['plainPassword'] ?? null;

        if (!\is_string($plainPassword) || '' === trim($plainPassword)) {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
    }
}
