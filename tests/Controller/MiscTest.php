<?php

namespace App\Tests\Controller;

/**
 * Tests fonctionnels — Pages diverses & mot de passe oublié
 *
 * Couvre : page d'accueil, ressources, 404, mot de passe oublié,
 *          réinitialisation de mot de passe.
 */
class MiscTest extends AbstractControllerTest
{
    // -------------------------------------------------------------------------
    // Page d'accueil
    // -------------------------------------------------------------------------

    public function testHomePageLoadsAndContainsBrandName(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('CESIZen', $this->client->getResponse()->getContent());
    }

    public function testHomePageContainsLinkToBreathing(): void
    {
        $this->client->request('GET', '/');

        $this->assertSelectorExists('a[href*="respiration"]');
    }

    // -------------------------------------------------------------------------
    // Page ressources
    // -------------------------------------------------------------------------

    public function testResourcesPageLoadsAndContainsTitle(): void
    {
        $this->client->request('GET', '/ressources-bien-etre');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Ressources', $this->client->getResponse()->getContent());
    }

    // -------------------------------------------------------------------------
    // Page 404
    // -------------------------------------------------------------------------

    public function testUnknownRouteReturns404(): void
    {
        $this->client->request('GET', '/cette-page-nexiste-pas');

        $this->assertResponseStatusCodeSame(404);
    }

    // -------------------------------------------------------------------------
    // Mot de passe oublié
    // -------------------------------------------------------------------------

    public function testForgotPasswordWithUnknownEmailRedirects(): void
    {
        $this->client->request('GET', '/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', [
            'email' => 'inconnu@cesizen-test.fr',
        ]);

        // Redirige dans tous les cas (anti-énumération d'emails)
        $this->assertResponseRedirects('/mot-de-passe-oublie');
    }

    public function testForgotPasswordWithKnownEmailRedirects(): void
    {
        $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');

        $this->client->request('GET', '/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', [
            'email' => 'user@cesizen-test.fr',
        ]);

        // Redirige dans tous les cas (anti-énumération d'emails)
        $this->assertResponseRedirects('/mot-de-passe-oublie');
    }

    // -------------------------------------------------------------------------
    // Réinitialisation de mot de passe
    // -------------------------------------------------------------------------

    public function testResetPasswordWithInvalidTokenRedirectsToForgotPassword(): void
    {
        $this->client->request('GET', '/reinitialiser-mot-de-passe/token_invalide_xyz');

        $this->assertResponseRedirects('/mot-de-passe-oublie');
    }

    public function testResetPasswordWithValidTokenLoadsPage(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');

        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->getEntityManager()->flush();

        $this->client->request('GET', '/reinitialiser-mot-de-passe/' . $token);

        $this->assertResponseIsSuccessful();
    }

    public function testResetPasswordWithValidTokenAndValidDataRedirectsToLogin(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');

        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->getEntityManager()->flush();

        $this->client->request('POST', '/reinitialiser-mot-de-passe/' . $token, [
            'password'         => 'NewPass456!',
            'password_confirm' => 'NewPass456!',
        ]);

        $this->assertResponseRedirects('/login');
    }

    public function testResetPasswordWithMismatchedPasswordsReturns422(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');

        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->getEntityManager()->flush();

        $this->client->request('POST', '/reinitialiser-mot-de-passe/' . $token, [
            'password'         => 'NewPass456!',
            'password_confirm' => 'AutreMotDePasse!',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }
}
