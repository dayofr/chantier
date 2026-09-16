<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\ListActivityProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'list_activity',
    description: 'Journal, du plus récent au plus ancien. Filtrable par projet, ticket/epic/initiative, session, type et texte. Utile pour reprendre le contexte ou retrouver une décision passée.',
    processor: ListActivityProcessor::class,
    structuredContent: false,
    validate: true,
    annotations: ['readOnlyHint' => true],
)]
final class ListActivity
{
    public ?string $project = null;

    /** Clé de ticket, d'epic ou d'initiative. Un epic inclut les entrées de ses tickets. */
    public ?string $ticket = null;

    /** Id de session MCP, tel que renvoyé dans le champ "session". */
    public ?string $session = null;

    #[ApiProperty(schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['created', 'status_changed', 'note', 'decision', 'progress', 'blocker', 'commit', 'test']]])]
    public array $type = [];

    /** Texte cherché : tous les mots doivent apparaître, casse et accents ignorés. */
    #[Assert\Length(max: 200)]
    public ?string $query = null;

    /** 1 à 200, défaut 30. */
    #[Assert\Range(min: 1, max: 200)]
    public int $limit = 30;
}
