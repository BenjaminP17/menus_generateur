<?php

namespace App\Controller;

use App\Database\CategoryRepository;
use App\Menu\Category;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liste des catégories principales, pour alimenter le formulaire de saisie d'un plat.
 */
final class CategoryController
{
    #[Route('/api/categories', name: 'api_categories_index', methods: ['GET'])]
    public function __invoke(CategoryRepository $categories): JsonResponse
    {
        return new JsonResponse([
            'categories' => array_map(
                static fn (Category $category): array => [
                    'id' => $category->id,
                    'libelle' => $category->label,
                ],
                $categories->findAll(),
            ),
        ]);
    }
}
