<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\ListProjectsProcessor;

#[McpTool(
    name: 'list_projects',
    description: 'Liste les projets avec leurs compteurs d\'avancement. Point de départ pour trouver la clé d\'un projet.',
    processor: ListProjectsProcessor::class,
    structuredContent: false,
    annotations: ['readOnlyHint' => true],
)]
final class ListProjects
{
    /** Filtre optionnel : planning, active, paused, done, archived. */
    public ?string $status = null;
}
