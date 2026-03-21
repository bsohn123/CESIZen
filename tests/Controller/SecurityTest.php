<?php

namespace App\Tests\Controller;

/**
 * Tests fonctionnels — Authentification
 *
 * Couvre : login, logout, inscription, mot de passe oublié,
 *          connexion avec compte inactif, credentials invalides.
 */
class SecurityTest extends AbstractControllerTest
{
    // -------------------------------------------------------------------------
    // Pages publiques d'authentification
    // -------------------------------------------------------------------------

    public function testLoginPageIsAccessible(): void
    {
        $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testRegisterPageIsAccessible(): void
    {
        $this->client->request('GET', '/inscription');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testForgotPasswordPageIsAccessible(): void
    {
        $this->client->request('GET', '/mot-de-passe-oublie');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    public function testLoginWithValidCredentialsRedirects(): void
    {
        $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'user@cesizen-test.fr',
            '_password' => 'TestPass123!',
        ]);

        $this->assertResponseRedirects();
    }

    public function testLoginWithWrongPasswordStaysOnLoginPage(): void
    {
        $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'user@cesizen-test.fr',
            '_password' => 'MauvaisMotDePasse!',
        ]);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertRouteSame('app_login');
    }

    public function testLoginWithUnknownEmailStaysOnLoginPage(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'inconnu@cesizen-test.fr',
            '_password' => 'TestPass123!',
        ]);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertRouteSame('app_login');
    }

    public function testLoginWithInactiveAccountIsRejected(): void
    {
        $this->createTestUser('inactive@cesizen-test.fr', 'TestPass123!', [], false);

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'inactive@cesizen-test.fr',
            '_password' => 'TestPass123!',
        ]);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertRouteSame('app_login');
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function testLogoutRedirects(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/logout');

        $this->assertResponseRedirects();
    }

    // -------------------------------------------------------------------------
    // Inscription
    // -------------------------------------------------------------------------

    public function testRegisterWithValidDataRedirects(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer mon compte', [
            'email'            => 'register_test@cesizen-test.fr',
            'username'         => 'testuser_reg',
            'password'         => 'TestPass123!',
            'password_confirm' => 'TestPass123!',
            'accept_terms'     => '1',
        ]);

        $this->assertResponseRedirects();
    }

    public function testRegisterWithExistingEmailShowsError(): void
    {
        $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');

        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer mon compte', [
            'email'            => 'user@cesizen-test.fr',
            'username'         => 'autreuser',
            'password'         => 'TestPass123!',
            'password_confirm' => 'TestPass123!',
            'accept_terms'     => '1',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testRegisterWithShortPasswordShowsError(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer mon compte', [
            'email'            => 'register_test@cesizen-test.fr',
            'username'         => 'testuser_short',
            'password'         => '123',
            'password_confirm' => '123',
            'accept_terms'     => '1',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    // -------------------------------------------------------------------------
    // Pages légales (accessibles sans connexion)
    // -------------------------------------------------------------------------

    public function testTermsPageIsAccessible(): void
    {
        $this->client->request('GET', '/conditions-utilisation');
        $this->assertResponseIsSuccessful();
    }

    public function testMentionsLegalesPageIsAccessible(): void
    {
        $this->client->request('GET', '/mentions-legales');
        $this->assertResponseIsSuccessful();
    }

    public function testPrivacyPageIsAccessible(): void
    {
        $this->client->request('GET', '/politique-de-confidentialite');
        $this->assertResponseIsSuccessful();
    }

    public function testDataPageIsAccessible(): void
    {
        $this->client->request('GET', '/gestion-des-donnees-personnelles');
        $this->assertResponseIsSuccessful();
    }
}
