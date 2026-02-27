<?php

namespace App\Controller;

use App\Entity\Launch;
use App\Entity\User;
use App\Repository\BreathingExerciseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BreathingController extends AbstractController
{
    #[Route('/respiration-guidee', name: 'app_breathing_guided', methods: ['GET'])]
    public function index(BreathingExerciseRepository $exerciseRepository): Response
    {
        return $this->render('breathing/index.html.twig', [
            'exercises' => $exerciseRepository->findActiveOrdered(),
        ]);
    }

    #[Route('/respiration-guidee/launch', name: 'app_breathing_launch', methods: ['POST'])]
    public function saveLaunch(
        Request $request,
        BreathingExerciseRepository $exerciseRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $csrfToken = (string) $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('breathing_launch', $csrfToken)) {
            return $this->json(['message' => 'Requete invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Payload invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $exerciseId = (int) ($payload['exercise_id'] ?? 0);
        $cycleCount = max(1, (int) ($payload['cycle_count'] ?? 0));
        $totalSeconds = max(1, (int) ($payload['total_seconds'] ?? 0));

        $exercise = $exerciseRepository->find($exerciseId);
        if (!$exercise || !$exercise->isActive()) {
            return $this->json(['message' => 'Exercice introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $duration = \DateTimeImmutable::createFromFormat('!H:i:s', gmdate('H:i:s', $totalSeconds % 86400));
        if (!$duration instanceof \DateTimeImmutable) {
            $duration = new \DateTimeImmutable('00:00:00');
        }

        $launch = (new Launch())
            ->setUser($user)
            ->setBreathingExercise($exercise)
            ->setCycleCount($cycleCount)
            ->setLaunchDate(new \DateTimeImmutable())
            ->setTotalDuration($duration);

        $entityManager->persist($launch);
        $entityManager->flush();

        return $this->json(['message' => 'Lancement enregistre.']);
    }
}
