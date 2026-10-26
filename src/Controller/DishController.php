<?php

namespace App\Controller;

use App\Database\CategoryRepository;
use App\Database\DishRepository;
use App\Database\DuplicateDishNameException;
use App\Menu\DishInput;
use App\Menu\DishInputValidator;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion de la base de plats (PRD §5.1).
 */
final class DishController
{
    public function __construct(
        private readonly DishRepository $dishes,
        private readonly CategoryRepository $categories,
        private readonly DishInputValidator $validator,
    ) {
    }

    #[Route('/api/plats', name: 'api_plats_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse(['plats' => DishPayload::fromDishes($this->dishes->findAll())]);
    }

    #[Route('/api/plats', name: 'api_plats_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $input = $this->readInput($request);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        try {
            $dish = $this->dishes->create($input);
        } catch (DuplicateDishNameException $duplicate) {
            return $this->duplicate($duplicate);
        }

        return new JsonResponse(
            DishPayload::fromDish($dish),
            Response::HTTP_CREATED,
            ['Location' => sprintf('/api/plats/%d', $dish->id)],
        );
    }

    #[Route('/api/plats/{id}', name: 'api_plats_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $dish = $this->dishes->find($id);

        return null === $dish
            ? $this->notFound()
            : new JsonResponse(DishPayload::fromDish($dish));
    }

    #[Route('/api/plats/{id}', name: 'api_plats_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $input = $this->readInput($request);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        try {
            $dish = $this->dishes->update($id, $input);
        } catch (DuplicateDishNameException $duplicate) {
            return $this->duplicate($duplicate);
        }

        return null === $dish
            ? $this->notFound()
            : new JsonResponse(DishPayload::fromDish($dish));
    }

    #[Route('/api/plats/{id}', name: 'api_plats_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): Response
    {
        if (!$this->dishes->delete($id)) {
            return $this->notFound();
        }

        // Response et non JsonResponse : un JsonResponse de corps null sérialise « {} ».
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Lit et valide le corps de la requête, ou renvoie la réponse d'erreur à retourner.
     */
    private function readInput(Request $request): DishInput|JsonResponse
    {
        try {
            // toArray() décode le JSON, refuse un corps vide ou une racine qui n'est pas un
            // tableau, et son type de retour est array.
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->invalidBody();
        }

        $result = $this->validator->validate($data);
        if (!$result->isValid()) {
            return ApiError::response(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Les données envoyées sont invalides.',
                $result->errors(),
            );
        }

        $input = $result->input();
        if (!$this->categories->exists($input->categoryId)) {
            return ApiError::response(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Les données envoyées sont invalides.',
                ['categorie_principale_id' => 'Catégorie principale inconnue.'],
            );
        }

        return $input;
    }

    private function invalidBody(): JsonResponse
    {
        return ApiError::response(
            Response::HTTP_BAD_REQUEST,
            "Le corps de la requête n'est pas un objet JSON valide.",
        );
    }

    private function notFound(): JsonResponse
    {
        return ApiError::response(Response::HTTP_NOT_FOUND, 'Plat introuvable.');
    }

    private function duplicate(DuplicateDishNameException $duplicate): JsonResponse
    {
        return ApiError::response(
            Response::HTTP_CONFLICT,
            'Un plat porte déjà ce nom.',
            ['nom' => sprintf('« %s » existe déjà.', $duplicate->existing()->name)],
        );
    }
}
