<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Format d'erreur unique de l'API (voir doc/api.md).
 *
 * « erreur » est toujours présent : le frontend peut l'afficher tel quel sans connaître le code
 * de statut. « champs » n'apparaît que pour les erreurs rattachées à un champ du formulaire, et
 * ses clés sont les noms de champs du payload JSON.
 */
final class ApiError
{
    /**
     * @param array<string, string> $fields
     */
    public static function response(int $status, string $message, array $fields = []): JsonResponse
    {
        $body = ['erreur' => $message];
        if ([] !== $fields) {
            $body['champs'] = $fields;
        }

        return new JsonResponse($body, $status);
    }
}
