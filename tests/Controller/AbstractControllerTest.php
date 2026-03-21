<?php

namespace App\Tests\Controller;

use App\Entity\BreathingExercise;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AbstractControllerTest extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        try {
            $em = $this->getEntityManager();
            $conn = $em->getConnection();

            // Supprimer les launches via DQL pour éviter les problèmes de FK
            $em->createQuery(
                'DELETE FROM App\Entity\Launch l WHERE l.user IN (
                    SELECT u FROM App\Entity\User u WHERE u.email IN (:emails)
                )'
            )->setParameter('emails', $this->getTestEmails())->execute();

            // Supprimer les users de test
            $em->createQuery(
                'DELETE FROM App\Entity\User u WHERE u.email IN (:emails)'
            )->setParameter('emails', $this->getTestEmails())->execute();

            // Supprimer les exercices de test
            $em->createQuery(
                'DELETE FROM App\Entity\BreathingExercise e WHERE e.name = :name'
            )->setParameter('name', 'Test 4-4-4')->execute();
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Emails créés pendant les tests — à surcharger si besoin.
     */
    protected function getTestEmails(): array
    {
        return [
            'user@cesizen-test.fr',
            'admin@cesizen-test.fr',
            'inactive@cesizen-test.fr',
            'register_test@cesizen-test.fr',
        ];
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    protected function createTestUser(
        string $email = 'user@cesizen-test.fr',
        string $password = 'TestPass123!',
        array $roles = [],
        bool $active = true
    ): User {
        $em = $this->getEntityManager();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            return $existing;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername('test_' . substr(md5($email), 0, 8));
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setRoles($roles);
        $user->setActive($active);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function loginAs(User $user): void
    {
        $this->client->loginUser($user);
    }

    protected function createBreathingExercise(
        string $name = 'Test 4-4-4',
        int $inhale = 4,
        int $hold = 4,
        int $exhale = 4,
        bool $active = true
    ): BreathingExercise {
        $em = $this->getEntityManager();

        $existing = $em->getRepository(BreathingExercise::class)->findOneBy(['name' => $name]);
        if ($existing) {
            return $existing;
        }

        $exercise = new BreathingExercise();
        $exercise->setName($name);
        $exercise->setInhaleDuration($inhale);
        $exercise->setHoldDuration($hold);
        $exercise->setExhaleDuration($exhale);
        $exercise->setActive($active);

        $em->persist($exercise);
        $em->flush();

        return $exercise;
    }

    protected function getCsrfTokenFromPage(string $selector, string $attribute = 'data-csrf-token'): string
    {
        return (string) $this->client->getCrawler()->filter($selector)->attr($attribute);
    }
}
