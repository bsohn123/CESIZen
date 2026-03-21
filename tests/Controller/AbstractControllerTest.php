<?php

namespace App\Tests\Controller;

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
        $em = $this->getEntityManager();
        $repo = $em->getRepository(User::class);

        foreach ($this->getTestEmails() as $email) {
            $user = $repo->findOneBy(['email' => $email]);
            if ($user) {
                $em->remove($user);
            }
        }
        $em->flush();

        parent::tearDown();
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
}
