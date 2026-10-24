<?php

namespace App\Controller;

use App\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vérifie que l'API répond et que la base SQLite est accessible.
 */
final class HealthController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(Database $database): JsonResponse
    {
        $database->connection()->query('SELECT 1');

        return new JsonResponse(['status' => 'ok']);
    }
}
