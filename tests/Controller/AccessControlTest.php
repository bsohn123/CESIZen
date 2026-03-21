<?php

namespace App\Tests\Controller;

/**
 * Tests fonctionnels — Contrôle d'accès
 *
 * Couvre : routes publiques, routes protégées (ROLE_USER),
 *          routes admin (ROLE_ADMIN), accès refusé (403).
 */
class AccessControlTest extends AbstractControllerTest
{
    // -------------------------------------------------------------------------
    // Routes publiques — accessibles sans connexion
    // -------------------------------------------------------------------------

    public function testHomeIsPublic(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testBreathingPageIsPublic(): void
    {
        $this->client->request('GET', '/respiration-guidee');
        $this->assertResponseIsSuccessful();
    }

    public function testResourcesPageIsPublic(): void
    {
        $this->client->request('GET', '/ressources-bien-etre');
        $this->assertResponseIsSuccessful();
    }

    // -------------------------------------------------------------------------
    // Routes protégées — redirigent vers /login si non connecté
    // -------------------------------------------------------------------------

    public function testTrackingRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/suivi-personnel');

        $this->assertResponseRedirects('/login');
    }

    public function testProfileRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/profile');

        $this->assertResponseRedirects('/login');
    }

    // -------------------------------------------------------------------------
    // Routes protégées — accessibles avec ROLE_USER
    // -------------------------------------------------------------------------

    public function testTrackingIsAccessibleForLoggedInUser(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/suivi-personnel');

        $this->assertResponseIsSuccessful();
    }

    public function testProfileIsAccessibleForLoggedInUser(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
    }

    // -------------------------------------------------------------------------
    // Routes admin — redirigent vers /login si non connecté
    // -------------------------------------------------------------------------

    public function testAdminRedirectsAnonymousToLogin(): void
    {
        $this->client->request('GET', '/admin');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    // -------------------------------------------------------------------------
    // Routes admin — refus 403 pour ROLE_USER
    // -------------------------------------------------------------------------

    public function testAdminIsForbiddenForRoleUser(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    // -------------------------------------------------------------------------
    // Routes admin — accessibles pour ROLE_ADMIN
    // -------------------------------------------------------------------------

    public function testAdminIsAccessibleForRoleAdmin(): void
    {
        $admin = $this->createTestUser('admin@cesizen-test.fr', 'AdminPass123!', ['ROLE_ADMIN']);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminUsersListIsAccessibleForRoleAdmin(): void
    {
        $admin = $this->createTestUser('admin@cesizen-test.fr', 'AdminPass123!', ['ROLE_ADMIN']);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/user');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminExercisesListIsAccessibleForRoleAdmin(): void
    {
        $admin = $this->createTestUser('admin@cesizen-test.fr', 'AdminPass123!', ['ROLE_ADMIN']);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/breathing-exercise');

        $this->assertResponseIsSuccessful();
    }
}
