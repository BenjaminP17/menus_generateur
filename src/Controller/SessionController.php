<?php

namespace App\Controller;

use App\Security\Credentials;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Connexion, déconnexion et état de la session du compte unique (jalon 1b, SEC-01). Ces trois
 * routes sont exemptées de la garde d'accès par App\EventListener\AuthenticationListener.
 */
final class SessionController
{
    public function __construct(
        private readonly Credentials $credentials,
        private readonly RateLimiterFactory $connexionLimiter,
    ) {
    }

    #[Route('/api/session', name: 'api_session_status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        return new JsonResponse(['connecte' => true === $request->getSession()->get('connecte')]);
    }

    #[Route('/api/session', name: 'api_session_login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        // Clé par IP : freine le brute force sans pénaliser les autres visiteurs (SEC-05).
        $limiter = $this->connexionLimiter->create($request->getClientIp() ?? 'inconnu');
        if (!$limiter->consume()->isAccepted()) {
            return ApiError::response(Response::HTTP_TOO_MANY_REQUESTS, 'Trop de tentatives, réessayez plus tard.');
        }

        try {
            // toArray() décode le JSON, refuse un corps vide ou une racine qui n'est pas un
            // tableau, et son type de retour est array.
            $data = $request->toArray();
        } catch (JsonException) {
            return ApiError::response(
                Response::HTTP_BAD_REQUEST,
                "Le corps de la requête n'est pas un objet JSON valide.",
            );
        }

        $identifiant = $data['identifiant'] ?? '';
        $motDePasse = $data['mot_de_passe'] ?? '';
        if (
            !is_string($identifiant)
            || !is_string($motDePasse)
            || !$this->credentials->verify($identifiant, $motDePasse)
        ) {
            return ApiError::response(Response::HTTP_UNAUTHORIZED, 'Identifiant ou mot de passe incorrect.');
        }

        // Regénère l'identifiant de session : protège contre la fixation de session.
        $request->getSession()->migrate();
        $request->getSession()->set('connecte', true);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/session', name: 'api_session_logout', methods: ['DELETE'])]
    public function logout(Request $request): Response
    {
        $request->getSession()->invalidate();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
