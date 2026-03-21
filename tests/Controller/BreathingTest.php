<?php

namespace App\Tests\Controller;

/**
 * Tests fonctionnels — Exercices de respiration
 *
 * Couvre : affichage de la page, enregistrement d'une session (API JSON),
 *          accès non authentifié, CSRF invalide, exercice introuvable.
 */
class BreathingTest extends AbstractControllerTest
{
    // -------------------------------------------------------------------------
    // Page publique
    // -------------------------------------------------------------------------

    public function testBreathingPageLoads(): void
    {
        $this->client->request('GET', '/respiration-guidee');

        $this->assertResponseIsSuccessful();
    }

    public function testBreathingPageContainsExerciseListWhenExercisesExist(): void
    {
        $this->createBreathingExercise('Test 4-4-4');

        $this->client->request('GET', '/respiration-guidee');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.exercise-item');
    }

    // -------------------------------------------------------------------------
    // POST /respiration-guidee/launch — sans authentification
    // -------------------------------------------------------------------------

    public function testSaveLaunchRequiresAuthentication(): void
    {
        $this->client->request('POST', '/respiration-guidee/launch', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{"exercise_id":1,"cycle_count":3,"total_seconds":60}');

        $this->assertResponseStatusCodeSame(401);
    }

    // -------------------------------------------------------------------------
    // POST /respiration-guidee/launch — avec authentification
    // -------------------------------------------------------------------------

    public function testSaveLaunchWithInvalidCsrfReturnsBadRequest(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $exercise = $this->createBreathingExercise('Test 4-4-4');

        $this->client->request('POST', '/respiration-guidee/launch', [], [], [
            'CONTENT_TYPE'      => 'application/json',
            'HTTP_X-CSRF-Token' => 'token_invalide',
        ], json_encode([
            'exercise_id'   => $exercise->getId(),
            'cycle_count'   => 3,
            'total_seconds' => 60,
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testSaveLaunchWithUnknownExerciseReturnsNotFound(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        // Établir une session via GET pour obtenir un token CSRF valide
        $this->client->request('GET', '/respiration-guidee');
        $csrfToken = $this->getCsrfTokenFromPage('#breathing-page');

        $this->client->request('POST', '/respiration-guidee/launch', [], [], [
            'CONTENT_TYPE'      => 'application/json',
            'HTTP_X-CSRF-Token' => $csrfToken,
        ], json_encode([
            'exercise_id'   => 999999,
            'cycle_count'   => 3,
            'total_seconds' => 60,
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testSaveLaunchWithValidDataReturnsSuccess(): void
    {
        $user     = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $exercise = $this->createBreathingExercise('Test 4-4-4');
        $this->loginAs($user);

        $this->client->request('GET', '/respiration-guidee');
        $csrfToken = $this->getCsrfTokenFromPage('#breathing-page');

        $this->client->request('POST', '/respiration-guidee/launch', [], [], [
            'CONTENT_TYPE'      => 'application/json',
            'HTTP_X-CSRF-Token' => $csrfToken,
        ], json_encode([
            'exercise_id'   => $exercise->getId(),
            'cycle_count'   => 5,
            'total_seconds' => 95,
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function testSaveLaunchWithInvalidJsonReturnsBadRequest(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/respiration-guidee');
        $csrfToken = $this->getCsrfTokenFromPage('#breathing-page');

        $this->client->request('POST', '/respiration-guidee/launch', [], [], [
            'CONTENT_TYPE'      => 'application/json',
            'HTTP_X-CSRF-Token' => $csrfToken,
        ], 'ceci_nest_pas_du_json');

        $this->assertResponseStatusCodeSame(400);
    }
}
