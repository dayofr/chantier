<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\SearchTicketsProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'search_tickets',
    description: 'Recherche des tickets par projet, epic, statut, priorité, type, label ou texte. Tri : priorité puis numéro.',
    processor: SearchTicketsProcessor::class,
    structuredContent: false,
    validate: true,
    annotations: ['readOnlyHint' => true],
)]
final class SearchTickets
{
    public ?string $project = null;

    public ?string $epic = null;

    #[ApiProperty(schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['backlog', 'todo', 'in_progress', 'in_review', 'blocked', 'done', 'cancelled']]])]
    public ?array $status = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']])]
    public ?string $priority = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['feature', 'bug', 'chore', 'refactor', 'spike', 'docs', 'test']])]
    public ?string $type = null;

    public ?string $label = null;

    /** Texte cherché dans la clé, le titre et la description. */
    public ?string $query = null;

    /** true = seulement les tickets sans epic. */
    public ?bool $orphan = null;

    /** 1 à 200, défaut 50. */
    #[Assert\Range(min: 1, max: 200)]
    public int $limit = 50;
}
