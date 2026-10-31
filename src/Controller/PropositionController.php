<?php

namespace App\Controller;

use App\Database\PropositionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Journal des propositions (PRD §5.4, règle de fraîcheur). L'écriture se fait à la validation
 * d'un menu, voir MenuController::validate().
 */
final class PropositionController
{
    public function __construct(private readonly PropositionRepository $propositions)
    {
    }

    /**
     * Vide l'historique, pour repartir de zéro après des essais. Les plats ne sont pas touchés.
     */
    #[Route('/api/propositions', name: 'api_propositions_clear', methods: ['DELETE'])]
    public function clear(): Response
    {
        $this->propositions->clear();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
