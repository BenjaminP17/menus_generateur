<?php

namespace App\Tests\Controller;

use App\Controller\PropositionController;
use App\Database\Database;
use App\Database\DishRepository;
use App\Tests\Support\DishFactory;
use App\Tests\Support\FunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PropositionController::class)]
final class PropositionControllerTest extends FunctionalTestCase
{
    public function testRequiresASession(): void
    {
        $this->client->request('DELETE', '/api/propositions');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testClearsTheJournalAndKeepsTheDishes(): void
    {
        $this->login();
        $dish = static::getContainer()->get(DishRepository::class)->create(DishFactory::rawInput('Plat A'));
        $pdo = static::getContainer()->get(Database::class)->connection();
        $pdo->prepare(
            "INSERT INTO proposition (plat_id, date_menu, jour, repas, origine, cree_le) VALUES (:plat_id, '2026-07-13', 1, 'dejeuner', 'suggestion', '2026-07-14T10:00:00+00:00')",
        )->execute(['plat_id' => $dish->id]);

        $this->client->request('DELETE', '/api/propositions');

        self::assertSame(204, $this->client->getResponse()->getStatusCode());
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM proposition')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM plat')->fetchColumn());
    }

    public function testClearingAnEmptyJournalStillSucceeds(): void
    {
        $this->login();

        $this->client->request('DELETE', '/api/propositions');

        self::assertSame(204, $this->client->getResponse()->getStatusCode());
    }
}
