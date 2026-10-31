<?php

namespace App\Tests\Controller;

use App\Controller\DishController;
use App\Tests\Support\FunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * SEC-02 : un identifiant de route trop long pour tenir dans un int PHP doit renvoyer une 404
 * propre, pas une 500 qui révèle le chemin absolu du serveur. Testé au niveau HTTP : la
 * conversion fautive se produit dans le routeur, avant même l'appel au contrôleur, donc
 * invisible à un test qui appelle DishController::show() directement.
 */
#[CoversClass(DishController::class)]
final class DishRouteBoundsTest extends FunctionalTestCase
{
    public function testAnOutOfRangeIdOnShowReturnsACleanNotFound(): void
    {
        $this->login();

        $this->client->request('GET', '/api/plats/99999999999999999999');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testAnOutOfRangeIdOnUpdateReturnsACleanNotFound(): void
    {
        $this->login();

        $this->client->request(
            'PUT',
            '/api/plats/99999999999999999999',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{}',
        );

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testAnOutOfRangeIdOnDeleteReturnsACleanNotFound(): void
    {
        $this->login();

        $this->client->request('DELETE', '/api/plats/99999999999999999999');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testANonNumericIdReturnsACleanNotFound(): void
    {
        $this->login();

        $this->client->request('GET', '/api/plats/abc');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testAnOrdinaryIdStillReachesTheController(): void
    {
        $this->login();

        $this->client->request('GET', '/api/plats/999');

        // Le contrôleur répond bien 404 « Plat introuvable » (JSON ApiError), preuve qu'on
        // atteint toujours le contrôleur pour un identifiant plausible.
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        self::assertSame('Plat introuvable.', $this->responseBody()['erreur']);
    }
}
