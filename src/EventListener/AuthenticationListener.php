<?php

namespace App\EventListener;

use App\Controller\ApiError;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Garde d'accès de l'API (SEC-01) : une session active est requise pour tout /api/*, à
 * l'exception de la sonde de santé et des endpoints de connexion eux-mêmes. Compte unique, pas
 * de table utilisateur (voir CLAUDE.md et App\Security\Credentials).
 */
final class AuthenticationListener implements EventSubscriberInterface
{
    private const EXEMPT_ROUTES = [
        'api_health',
        'api_session_status',
        'api_session_login',
        'api_session_logout',
    ];

    public static function getSubscribedEvents(): array
    {
        // Priorité inférieure à celle du routeur (32), pour que « _route » soit déjà résolu.
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if (in_array($request->attributes->get('_route'), self::EXEMPT_ROUTES, true)) {
            return;
        }

        if (true !== $request->getSession()->get('connecte')) {
            $event->setResponse(ApiError::response(Response::HTTP_UNAUTHORIZED, 'Authentification requise.'));
        }
    }
}
