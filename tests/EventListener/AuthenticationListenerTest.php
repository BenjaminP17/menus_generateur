<?php

namespace App\Tests\EventListener;

use App\EventListener\AuthenticationListener;
use App\Tests\Support\FunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * SEC-01 : toute l'API doit refuser l'accès sans session active, sauf les routes exemptées.
 */
#[CoversClass(AuthenticationListener::class)]
final class AuthenticationListenerTest extends FunctionalTestCase
{
    public function testApiRoutesAreBlockedWithoutASession(): void
    {
        $this->client->request('GET', '/api/plats');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertSame('Authentification requise.', $this->responseBody()['erreur']);
    }

    public function testApiRoutesBecomeAccessibleAfterLogin(): void
    {
        $this->login();

        $this->client->request('GET', '/api/plats');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testHealthCheckIsExemptWithoutASession(): void
    {
        $this->client->request('GET', '/api/health');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testSessionStatusIsExemptWithoutASession(): void
    {
        $this->client->request('GET', '/api/session');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertFalse($this->responseBody()['connecte']);
    }

    public function testLogoutRemovesAccess(): void
    {
        $this->login();
        $this->client->request('DELETE', '/api/session');
        self::assertSame(204, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/plats');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }
}
