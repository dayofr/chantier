<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\CreateTicketsProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'create_tickets',
    description: 'Crée un ou plusieurs tickets d\'un coup, avec sous-tâches et dépendances. blockedBy accepte des clés existantes (AETH-12) ou l\'index d\'un ticket du même lot ("#0" = premier). Renvoie les clés créées.',
    processor: CreateTicketsProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class CreateTickets
{
    #[Assert\NotBlank]
    public string $project = '';

    /** Epic par défaut pour tous les tickets du lot, ex. AETH-E2. */
    public ?string $epic = null;

    #[ApiProperty(schema: [
        'type' => 'array',
        'minItems' => 1,
        'items' => [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string', 'description' => 'Markdown : contexte, critères d\'acceptation.'],
                'type' => ['type' => 'string', 'enum' => ['feature', 'bug', 'chore', 'refactor', 'spike', 'docs', 'test']],
                'status' => ['type' => 'string', 'enum' => ['backlog', 'todo', 'in_progress', 'in_review', 'blocked', 'done', 'cancelled']],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                'storyPoints' => ['type' => 'integer', 'minimum' => 0],
                'labels' => ['type' => 'array', 'items' => ['type' => 'string']],
                'epic' => ['type' => 'string', 'description' => 'Remplace l\'epic par défaut ; "none" pour aucun.'],
                'subTasks' => ['type' => 'array', 'items' => ['type' => 'string']],
                'blockedBy' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Clés (AETH-12) ou index du lot (#0).'],
            ],
        ],
    ])]
    #[Assert\Count(min: 1, max: 100)]
    public array $tickets = [];
}
