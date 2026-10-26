<?php

namespace App\Tests\Controller;

use App\Controller\ApiError;
use App\Controller\DishController;
use App\Controller\DishPayload;
use App\Database\CategoryRepository;
use App\Menu\DishInputValidator;
use App\Menu\DishNameNormalizer;
use App\Tests\Support\DishFactory;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(DishController::class)]
#[CoversClass(DishPayload::class)]
#[CoversClass(ApiError::class)]
final class DishControllerTest extends TestCase
{
    private \PDO $pdo;

    private FixedClock $clock;

    private DishController $controller;

    protected function setUp(): void
    {
        $this->pdo = InMemoryDatabase::withSchema();
        $this->clock = new FixedClock();
        $this->controller = new DishController(
            DishFactory::repository($this->pdo, $this->clock),
            new CategoryRepository($this->pdo),
            new DishInputValidator(new DishNameNormalizer()),
        );
    }

    public function testIndexIsEmptyOnAFreshBase(): void
    {
        $response = $this->controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(['plats' => []], $this->body($response));
    }

    public function testIndexListsDishesSortedByName(): void
    {
        foreach (['Soupe de potiron', 'Épinards à la crème', 'avocat farci'] as $name) {
            $this->create(['nom' => $name]);
        }

        /** @var array{plats: list<array{nom: string}>} $body */
        $body = $this->body($this->controller->index());

        $this->assertSame(
            ['avocat farci', 'Épinards à la crème', 'Soupe de potiron'],
            array_column($body['plats'], 'nom'),
        );
    }

    public function testCreateReturnsTheStoredDish(): void
    {
        $response = $this->create(['nom' => 'Quiche lorraine', 'convient_diner' => false]);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $body = $this->body($response);
        $this->assertSame('/api/plats/'.$body['id'], $response->headers->get('Location'));
        $this->assertSame('Quiche lorraine', $body['nom']);
        $this->assertTrue($body['convient_dejeuner']);
        $this->assertFalse($body['convient_diner']);
        $this->assertSame('2026-10-25T12:00:00+00:00', $body['cree_le']);
        $this->assertSame('2026-10-25T12:00:00+00:00', $body['modifie_le']);
        $this->assertArrayNotHasKey('nom_normalise', $body);
    }

    public function testCreateRejectsAnInvalidPayload(): void
    {
        $response = $this->create(['nom' => '   ']);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $body = $this->body($response);
        $this->assertNotSame('', $body['erreur']);
        $this->assertArrayHasKey('nom', $body['champs']);
    }

    public function testCreateRejectsAnUnknownCategory(): void
    {
        $response = $this->create(['categorie_principale_id' => 999]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertArrayHasKey('categorie_principale_id', $this->body($response)['champs']);
    }

    public function testCreateRejectsADuplicateName(): void
    {
        $this->create(['nom' => 'Quiche']);

        $response = $this->create(['nom' => 'QUÎCHE']);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertArrayHasKey('nom', $this->body($response)['champs']);
    }

    /**
     * Protège l'import de JsonException : HttpFoundation lève la sienne, pas \JsonException.
     */
    public function testCreateRejectsAMalformedBody(): void
    {
        $response = $this->controller->create(DishFactory::request('POST', '/api/plats', '{oops'));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertArrayHasKey('erreur', $this->body($response));
    }

    public function testCreateRejectsAnEmptyBody(): void
    {
        $response = $this->controller->create(DishFactory::request('POST', '/api/plats', ''));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testCreateRejectsABodyThatIsNotAnObject(): void
    {
        $response = $this->controller->create(DishFactory::request('POST', '/api/plats', '"texte"'));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testShowReturnsTheDish(): void
    {
        $id = $this->body($this->create())['id'];

        $response = $this->controller->show($id);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame($id, $this->body($response)['id']);
    }

    public function testShowReportsAnUnknownDish(): void
    {
        $response = $this->controller->show(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Plat introuvable.', $this->body($response)['erreur']);
    }

    public function testUpdateReturnsTheUpdatedDish(): void
    {
        $created = $this->body($this->create());
        $this->clock->advance('+2 hours');

        $response = $this->update($created['id'], ['nom' => 'Gratin revisité', 'poids' => 3]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->body($response);
        $this->assertSame('Gratin revisité', $body['nom']);
        $this->assertSame(3, $body['poids']);
        $this->assertSame($created['cree_le'], $body['cree_le']);
        $this->assertSame('2026-10-25T14:00:00+00:00', $body['modifie_le']);
    }

    public function testUpdateKeepingTheSameNameIsAllowed(): void
    {
        $id = $this->body($this->create())['id'];

        $response = $this->update($id, ['poids' => 1]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testUpdateReportsAnUnknownDish(): void
    {
        $response = $this->update(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testUpdateRejectsTheNameOfAnotherDish(): void
    {
        $this->create(['nom' => 'Quiche']);
        $other = $this->body($this->create(['nom' => 'Soupe de potiron']));

        $response = $this->update($other['id'], ['nom' => 'Quiche']);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testUpdateRejectsAnInvalidPayload(): void
    {
        $id = $this->body($this->create())['id'];

        $response = $this->update($id, ['saison' => 'ete']);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertArrayHasKey('saison', $this->body($response)['champs']);
    }

    public function testDeleteReturnsAnEmptyResponse(): void
    {
        $id = $this->body($this->create())['id'];

        $response = $this->controller->delete($id);

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('', (string) $response->getContent());
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller->show($id)->getStatusCode());
    }

    public function testDeleteReportsAnUnknownDish(): void
    {
        $response = $this->controller->delete(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function create(array $overrides = []): Response
    {
        return $this->controller->create(
            DishFactory::request('POST', '/api/plats', DishFactory::payload($overrides)),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function update(int $id, array $overrides = []): Response
    {
        return $this->controller->update(
            $id,
            DishFactory::request('PUT', '/api/plats/'.$id, DishFactory::payload($overrides)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
