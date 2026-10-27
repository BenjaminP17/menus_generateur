<?php

namespace App\Tests\Controller;

use App\Controller\SessionController;
use App\Tests\Support\FunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SessionController::class)]
final class SessionControllerTest extends FunctionalTestCase
{
    public function testLoginWithTheRightCredentialsOpensASession(): void
    {
        $this->login();

        $this->client->request('GET', '/api/session');
        self::assertTrue($this->responseBody()['connecte']);
    }

    public function testLoginRejectsAWrongPassword(): void
    {
        $this->client->request(
            'POST',
            '/api/session',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['identifiant' => self::TEST_USER, 'mot_de_passe' => 'mauvais'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertSame('Identifiant ou mot de passe incorrect.', $this->responseBody()['erreur']);
    }

    public function testLoginRejectsAnUnknownIdentifiant(): void
    {
        $this->client->request(
            'POST',
            '/api/session',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['identifiant' => 'quelquun-dautre', 'mot_de_passe' => self::TEST_PASSWORD], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testLoginRejectsAMalformedBody(): void
    {
        $this->client->request('POST', '/api/session', server: ['CONTENT_TYPE' => 'application/json'], content: '{oops');

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testLoginIsRateLimitedAfterFiveFailedAttempts(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->client->request(
                'POST',
                '/api/session',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['identifiant' => self::TEST_USER, 'mot_de_passe' => 'mauvais'], \JSON_THROW_ON_ERROR),
            );
            self::assertSame(401, $this->client->getResponse()->getStatusCode());
        }

        // La sixième tentative est bloquée par le limiteur, même avec les bons identifiants.
        $this->client->request(
            'POST',
            '/api/session',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['identifiant' => self::TEST_USER, 'mot_de_passe' => self::TEST_PASSWORD], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(429, $this->client->getResponse()->getStatusCode());
    }

    public function testLogoutClosesTheSession(): void
    {
        $this->login();

        $this->client->request('DELETE', '/api/session');

        self::assertSame(204, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/session');
        self::assertFalse($this->responseBody()['connecte']);
    }
}
