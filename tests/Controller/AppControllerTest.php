<?php

namespace App\Tests\Controller;

use App\Controller\AppController;
use App\Tests\Support\FunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AppController::class)]
final class AppControllerTest extends FunctionalTestCase
{
    public function testRootServesTheFrontendPageWithoutSession(): void
    {
        $this->client->request('GET', '/');

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('/js/app.js', (string) file_get_contents(__DIR__.'/../../public/index.html'));
    }
}
