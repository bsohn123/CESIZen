<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sonde de santé consommée par le healthcheck Docker et par la supervision.
 *
 * Le déploiement ne bascule le trafic sur les nouveaux conteneurs que lorsque
 * cette route répond 200 : une image qui démarre mais ne joint pas sa base ne
 * reçoit jamais de requête utilisateur (voir deploy.sh).
 *
 * La réponse ne divulgue ni version applicative, ni détail d'infrastructure.
 */
final class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function index(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            return new JsonResponse(['status' => 'degraded'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
