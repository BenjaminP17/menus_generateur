<?php

namespace App\Tests\Controller;

use App\Controller\CategoryController;
use App\Database\CategoryRepository;
use App\Tests\Support\InMemoryDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CategoryController::class)]
final class CategoryControllerTest extends TestCase
{
    public function testListsTheReferenceCategories(): void
    {
        $controller = new CategoryController();

        $response = $controller(new CategoryRepository(InMemoryDatabase::withSchema()));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        /** @var array{categories: list<array{id: int, libelle: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertCount(8, $body['categories']);
        $this->assertSame(['id' => 1, 'libelle' => 'pâtes'], $body['categories'][0]);
        $this->assertSame('autre', $body['categories'][7]['libelle']);
    }
}
