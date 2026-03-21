<?php

namespace App\Tests\Controller;

use App\Entity\BreathingExercise;
use App\Entity\Launch;

/**
 * Tests fonctionnels — Suivi personnel
 *
 * Couvre : accès authentifié, affichage des KPIs, présence des sections,
 *          historique vide, historique avec sessions.
 *          Tests unitaires des méthodes statiques (formatDurationLabel, dayLabel).
 */
class TrackingTest extends AbstractControllerTest
{
    // -------------------------------------------------------------------------
    // Accès et rendu de la page
    // -------------------------------------------------------------------------

    public function testTrackingPageLoadsForAuthenticatedUser(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/suivi-personnel');

        $this->assertResponseIsSuccessful();
    }

    public function testTrackingPageContainsKpiSection(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/suivi-personnel');

        $this->assertSelectorExists('.kpi-grid');
    }

    public function testTrackingPageContainsGoalSection(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/suivi-personnel');

        $this->assertSelectorExists('.goal-progress');
    }

    public function testTrackingPageContainsHistorySection(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/suivi-personnel');

        $this->assertStringContainsString('Historique', $this->client->getResponse()->getContent());
    }

    public function testTrackingPageShowsEmptyMessageWhenNoSessions(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $this->loginAs($user);

        $this->client->request('GET', '/suivi-personnel');

        $this->assertSelectorExists('.empty');
    }

    public function testTrackingPageShowsSessionInHistoryAfterLaunch(): void
    {
        $user     = $this->createTestUser('user@cesizen-test.fr', 'TestPass123!');
        $exercise = $this->createBreathingExercise('Test 4-4-4');

        $this->createLaunch($user, $exercise);

        $this->loginAs($user);
        $this->client->request('GET', '/suivi-personnel');

        $this->assertSelectorExists('table tbody tr');
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    private function createLaunch(
        \App\Entity\User $user,
        BreathingExercise $exercise,
        int $cycles = 5,
        int $seconds = 120
    ): Launch {
        $em = $this->getEntityManager();

        $duration = \DateTimeImmutable::createFromFormat('!H:i:s', gmdate('H:i:s', $seconds));

        $launch = (new Launch())
            ->setUser($user)
            ->setBreathingExercise($exercise)
            ->setCycleCount($cycles)
            ->setLaunchDate(new \DateTimeImmutable())
            ->setTotalDuration($duration ?: new \DateTimeImmutable('00:00:00'));

        $em->persist($launch);
        $em->flush();

        return $launch;
    }

}
