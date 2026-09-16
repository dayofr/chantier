<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\GetProjectProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'get_project',
    description: 'Arbre complet d\'un projet : initiatives > epics > tickets, tickets orphelins, avancement à chaque niveau et points d\'attention (goulots, tickets en cours sans mouvement, bloqués, sans epic).',
    processor: GetProjectProcessor::class,
    structuredContent: false,
    validate: true,
    annotations: ['readOnlyHint' => true],
)]
final class GetProject
{
    /** Clé du projet, ex. AETH. */
    #[Assert\NotBlank]
    public string $project = '';

    /** Inclure les tickets terminés ou annulés. Défaut : false. */
    public bool $includeClosed = false;
}
