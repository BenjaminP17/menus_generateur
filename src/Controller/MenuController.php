<?php

namespace App\Controller;

use App\Database\DishRepository;
use App\Menu\Dish;
use App\Menu\Menu;
use App\Menu\MenuEdit;
use App\Menu\MenuEditValidator;
use App\Menu\MenuGenerator;
use App\Menu\MenuMeal;
use App\Menu\MenuRequest;
use App\Menu\MenuRequestValidator;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Génération et modification du menu (PRD §5.2 à §5.4).
 *
 * Rien n'est stocké côté serveur : le navigateur renvoie à chaque demande les réglages et le menu
 * en cours, et reçoit le menu complet en réponse.
 */
final class MenuController
{
    public function __construct(
        private readonly DishRepository $dishes,
        private readonly MenuRequestValidator $requestValidator,
        private readonly MenuEditValidator $editValidator,
        private readonly MenuGenerator $generator,
    ) {
    }

    #[Route('/api/menu', name: 'api_menu_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $menuRequest = $this->readRequest($request);
        if ($menuRequest instanceof JsonResponse) {
            return $menuRequest;
        }

        return $this->menu($this->generator->generate($this->dishes->findAll(), $menuRequest));
    }

    /**
     * Sans `cible`, tout le menu est régénéré ; avec, seul ce repas change.
     */
    #[Route('/api/menu/regenerer', name: 'api_menu_regenerate', methods: ['POST'])]
    public function regenerate(Request $request): JsonResponse
    {
        $read = $this->readEdit($request, targetRequired: false, dishRequired: false);
        if ($read instanceof JsonResponse) {
            return $read;
        }
        [$menuRequest, $edit, $dishes] = $read;

        if (!$edit->hasTarget()) {
            return $this->menu($this->generator->generate(array_values($dishes), $menuRequest));
        }

        $menu = $this->generator->regenerate(
            array_values($dishes),
            $menuRequest,
            $this->currentMeals($edit, $dishes),
            (int) $edit->targetDay,
            $edit->targetKind ?? throw new \LogicException('Cible sans repas.'),
        );

        return null === $menu
            ? ApiError::response(Response::HTTP_CONFLICT, 'Aucun autre plat ne convient pour ce repas.')
            : $this->menu($menu);
    }

    #[Route('/api/menu/choisir', name: 'api_menu_choose', methods: ['POST'])]
    public function choose(Request $request): JsonResponse
    {
        $read = $this->readEdit($request, targetRequired: true, dishRequired: true);
        if ($read instanceof JsonResponse) {
            return $read;
        }
        [$menuRequest, $edit, $dishes] = $read;

        $chosen = $dishes[(int) $edit->dishId] ?? null;
        if (null === $chosen) {
            return ApiError::response(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Les données envoyées sont invalides.',
                ['plat_id' => 'Plat introuvable.'],
            );
        }

        return $this->menu($this->generator->place(
            $this->currentMeals($edit, $dishes),
            $menuRequest,
            new MenuMeal((int) $edit->targetDay, $edit->targetKind ?? throw new \LogicException('Cible sans repas.'), $chosen),
        ));
    }

    private function menu(Menu $menu): JsonResponse
    {
        return new JsonResponse(['menu' => MenuPayload::fromMenu($menu)]);
    }

    /**
     * Repas du menu en cours dont le plat existe encore : un plat supprimé entre-temps (PRD §5.1)
     * laisse un trou, que l'évaluation signale.
     *
     * @param array<int, Dish> $dishes plats par identifiant
     *
     * @return list<MenuMeal>
     */
    private function currentMeals(MenuEdit $edit, array $dishes): array
    {
        $meals = [];
        foreach ($edit->placements as $placement) {
            if (isset($dishes[$placement->dishId])) {
                $meals[] = new MenuMeal($placement->day, $placement->kind, $dishes[$placement->dishId]);
            }
        }

        return $meals;
    }

    /**
     * @return MenuRequest|JsonResponse les réglages, ou la réponse d'erreur à retourner
     */
    private function readRequest(Request $request): MenuRequest|JsonResponse
    {
        $data = $this->readBody($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $result = $this->requestValidator->validate($data);

        return $result->isValid() ? $result->request() : $this->invalid($result->errors());
    }

    /**
     * @return array{MenuRequest, MenuEdit, array<int, Dish>}|JsonResponse
     */
    private function readEdit(Request $request, bool $targetRequired, bool $dishRequired): array|JsonResponse
    {
        $data = $this->readBody($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $result = $this->requestValidator->validate($data);
        if (!$result->isValid()) {
            return $this->invalid($result->errors());
        }

        $edit = $this->editValidator->validate($data, $result->request(), $targetRequired, $dishRequired);
        if (!$edit->isValid()) {
            return $this->invalid($edit->errors());
        }

        $dishes = [];
        foreach ($this->dishes->findAll() as $dish) {
            $dishes[$dish->id] = $dish;
        }

        return [$result->request(), $edit->edit(), $dishes];
    }

    /**
     * @return array<array-key, mixed>|JsonResponse
     */
    private function readBody(Request $request): array|JsonResponse
    {
        try {
            return $request->toArray();
        } catch (JsonException) {
            return ApiError::response(
                Response::HTTP_BAD_REQUEST,
                "Le corps de la requête n'est pas un objet JSON valide.",
            );
        }
    }

    /**
     * @param array<string, string> $errors
     */
    private function invalid(array $errors): JsonResponse
    {
        return ApiError::response(Response::HTTP_UNPROCESSABLE_ENTITY, 'Les données envoyées sont invalides.', $errors);
    }
}
