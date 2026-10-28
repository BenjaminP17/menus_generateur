<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sert la page unique du frontend. Le serveur web donne la priorité à `index.php` sur
 * `index.html` à la racine : sans cette route, `/` ne renverrait pas la page.
 */
final class AppController
{
    #[Route('/', name: 'app_index', methods: ['GET'])]
    public function __invoke(
        #[Autowire('%kernel.project_dir%/public/index.html')]
        string $page,
    ): Response {
        $response = new BinaryFileResponse($page, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }
}
