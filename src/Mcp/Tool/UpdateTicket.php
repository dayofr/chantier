<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\UpdateTicketProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'update_ticket',
    description: 'Modifie un ticket : statut, priorité, contenu, epic. Seuls les champs fournis changent. Les changements de statut sont journalisés automatiquement ; "comment" ajoute une note au journal.',
    processor: UpdateTicketProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class UpdateTicket
{
    use SessionAwareTrait;

    /** Clé du ticket, ex. AETH-12. */
    #[Assert\NotBlank]
    public string $ticket = '';

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['backlog', 'todo', 'in_progress', 'in_review', 'blocked', 'done', 'cancelled']])]
    public ?string $status = null;

    public ?string $title = null;

    /** Markdown. Remplace la description existante. */
    public ?string $description = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']])]
    public ?string $priority = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['feature', 'bug', 'chore', 'refactor', 'spike', 'docs', 'test']])]
    public ?string $type = null;

    /** Remplace la liste des labels. */
    #[ApiProperty(schema: ['type' => 'array', 'items' => ['type' => 'string']])]
    public ?array $labels = null;

    /** Clé de l'epic, ou "none" pour détacher. */
    public ?string $epic = null;

    public ?string $assignee = null;

    /** Note ajoutée au journal avec la modification (pourquoi, résultat). */
    public ?string $comment = null;
}
