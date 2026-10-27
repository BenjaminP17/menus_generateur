<?php

namespace App\Tests\Support;

use App\Database\Database;
use App\Database\SchemaInstaller;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base commune aux tests fonctionnels qui démarrent le kernel (jalon 1b : authentification,
 * SEC-02). Nécessaire pour ces deux sujets car ils vivent dans la configuration des routes et
 * dans un listener kernel.request, que les tests du jalon 1 (qui appellent les contrôleurs
 * directement) ne traversent jamais.
 *
 * La base SQLite de test (var/data/menus.test.sqlite, voir le when@test de
 * config/services.yaml) est effacée et réinstallée avant chaque test, pour partir d'une base
 * vide sans toucher à var/data/menus.sqlite.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    /** Identifiants du compte de test, voir .env.test. */
    protected const TEST_USER = 'test';
    protected const TEST_PASSWORD = 'mot-de-passe-test';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();
        $database = $container->get(Database::class);

        foreach ([$database->path(), $database->path().'-journal', $database->path().'-wal', $database->path().'-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $container->get(SchemaInstaller::class)->install($database->connection());

        // Évite qu'un test de limiteur (SessionControllerTest) laisse des tentatives comptées
        // pour le test suivant : même pool de cache, même IP de client de test.
        $container->get('cache.rate_limiter')->clear();
    }

    /**
     * Connecte le client de test avec le compte unique de test, et vérifie que ça fonctionne.
     */
    protected function login(): void
    {
        $this->client->request(
            'POST',
            '/api/session',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['identifiant' => self::TEST_USER, 'mot_de_passe' => self::TEST_PASSWORD], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(204, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseBody(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        return $decoded;
    }
}
