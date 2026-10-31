<?php

namespace App\Tests\Database;

use App\Database\CategoryRepository;
use App\Menu\Category;
use App\Tests\Support\InMemoryDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryRepository::class)]
#[CoversClass(Category::class)]
final class CategoryRepositoryTest extends TestCase
{
    public function testListsTheEightReferenceCategories(): void
    {
        $categories = (new CategoryRepository(InMemoryDatabase::withSchema()))->findAll();

        $this->assertSame(
            ['pâtes', 'riz / céréales', 'viande', 'poisson', 'œufs', 'légumes', 'soupe', 'autre'],
            array_map(static fn (Category $category): string => $category->label, $categories),
        );
        $this->assertSame(1, $categories[0]->id);
    }

    public function testExistsOnlyForAKnownCategory(): void
    {
        $repository = new CategoryRepository(InMemoryDatabase::withSchema());

        $this->assertTrue($repository->exists(1));
        $this->assertFalse($repository->exists(999));
    }
}
