<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\ListActivityProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'list_activity',
    description: 'Journal récent, du plus récent au plus ancien. Utile pour reprendre le contexte d\'une session précédente.',
    processor: ListActivityProcessor::class,
    structuredContent: false,
    validate: true,
    annotations: ['readOnlyHint' => true],
)]
final class ListActivity
{
    public ?string $project = null;

    public ?string $ticket = null;

    /** Id de session MCP, tel que renvoyé dans le champ "session". */
    public ?string $session = null;

    /** 1 à 200, défaut 30. */
    #[Assert\Range(min: 1, max: 200)]
    public int $limit = 30;
}
