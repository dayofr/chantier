<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\GetProjectProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'get_project',
    description: 'Arbre complet d\'un projet : initiatives > epics > tickets, tickets orphelins et avancement à chaque niveau.',
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
