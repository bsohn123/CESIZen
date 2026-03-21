<?php

namespace App\Tests\Controller;

/**
 * Tests fonctionnels — Profil utilisateur
 *
 * Couvre : affichage, modification des infos, changement de mot de passe,
 *          suppression de compte, CSRF invalide.
 */
class ProfileTest extends AbstractControllerTest
{
    // -------------------------------------------------------------------------
    // Affichage
    // -------------------------------------------------------------------------

    public function testProfilePageLoadsForAuthenticatedUser(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
    }

    public function testProfilePageDisplaysUsername(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');

        $this->assertStringContainsString(
            $user->getUsername(),
            $this->client->getResponse()->getContent()
        );
    }

    public function testProfilePageDisplaysEmail(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');

        $this->assertStringContainsString(
            $user->getEmail(),
            $this->client->getResponse()->getContent()
        );
    }

    // -------------------------------------------------------------------------
    // Modification des informations
    // -------------------------------------------------------------------------

    public function testEditProfileWithValidDataRedirects(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');
        $token = $this->getCsrfTokenFromPage('form[action$="profile/edit"] input[name="_token"]', 'value');

        $this->client->request('POST', '/profile/edit', [
            '_token'   => $token,
            'email'    => 'user@cesizen-test.fr',
            'username' => 'nouveau_pseudo',
        ]);

        $this->assertResponseRedirects('/profile');
    }

    public function testEditProfileWithInvalidCsrfRedirects(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('POST', '/profile/edit', [
            '_token'   => 'token_invalide',
            'email'    => 'user@cesizen-test.fr',
            'username' => 'pseudo_test',
        ]);

        $this->assertResponseRedirects();
    }

    public function testEditProfileWithEmptyEmailRedirects(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');
        $token = $this->getCsrfTokenFromPage('form[action$="profile/edit"] input[name="_token"]', 'value');

        $this->client->request('POST', '/profile/edit', [
            '_token'   => $token,
            'email'    => '',
            'username' => 'pseudo_test',
        ]);

        $this->assertResponseRedirects();
    }

    public function testEditProfileWithDuplicateEmailRedirects(): void
    {
        $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $admin = $this->createTestUser('admin@cesizen-test.fr', 'AdminPass123!', ['ROLE_ADMIN']);
        $this->loginAs($admin);

        $this->client->request('GET', '/profile');
        $token = $this->getCsrfTokenFromPage('form[action$="profile/edit"] input[name="_token"]', 'value');

        // Tenter de prendre l'email de l'autre utilisateur
        $this->client->request('POST', '/profile/edit', [
            '_token'   => $token,
            'email'    => 'user@cesizen-test.fr',
            'username' => 'pseudo_admin',
        ]);

        $this->assertResponseRedirects();
    }

    // -------------------------------------------------------------------------
    // Changement de mot de passe
    // -------------------------------------------------------------------------

    public function testChangePasswordWithValidDataRedirects(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');
        $token = $this->getCsrfTokenFromPage('form[action$="change-password"] input[name="_token"]', 'value');

        $this->client->request('POST', '/profile/change-password', [
            '_token'           => $token,
            'current_password' => 'TestPass123!',
            'new_password'     => 'NewPass456!',
            'confirm_password' => 'NewPass456!',
        ]);

        $this->assertResponseRedirects('/profile');
    }

    public function testChangePasswordWithWrongCurrentPasswordRedirects(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');
        $token = $this->getCsrfTokenFromPage('form[action$="change-password"] input[name="_token"]', 'value');

        $this->client->request('POST', '/profile/change-password', [
            '_token'           => $token,
            'current_password' => 'MauvaisMotDePasse!',
            'new_password'     => 'NewPass456!',
            'confirm_password' => 'NewPass456!',
        ]);

        $this->assertResponseRedirects();
    }

    public function testChangePasswordWithMismatchedPasswordsRedirects(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');
        $token = $this->getCsrfTokenFromPage('form[action$="change-password"] input[name="_token"]', 'value');

        $this->client->request('POST', '/profile/change-password', [
            '_token'           => $token,
            'current_password' => 'TestPass123!',
            'new_password'     => 'NewPass456!',
            'confirm_password' => 'AutreMotDePasse!',
        ]);

        $this->assertResponseRedirects();
    }

    public function testChangePasswordWithShortNewPasswordRedirects(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');
        $token = $this->getCsrfTokenFromPage('form[action$="change-password"] input[name="_token"]', 'value');

        $this->client->request('POST', '/profile/change-password', [
            '_token'           => $token,
            'current_password' => 'TestPass123!',
            'new_password'     => '123',
            'confirm_password' => '123',
        ]);

        $this->assertResponseRedirects();
    }

    // -------------------------------------------------------------------------
    // Suppression de compte
    // -------------------------------------------------------------------------

    public function testDeleteAccountWithValidCsrfRedirectsToLogin(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');
        $token = $this->getCsrfTokenFromPage('form[action$="profile/delete"] input[name="_token"]', 'value');

        $this->client->request('POST', '/profile/delete', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/login');
    }

    public function testDeleteAccountWithInvalidCsrfRedirects(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('POST', '/profile/delete', [
            '_token' => 'token_invalide',
        ]);

        $this->assertResponseRedirects();
    }
}
