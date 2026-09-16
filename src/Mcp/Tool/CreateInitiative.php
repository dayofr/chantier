<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\CreateInitiativeProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'create_initiative',
    description: 'Crée une initiative (grand objectif) dans un projet. Elle regroupe des epics.',
    processor: CreateInitiativeProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class CreateInitiative
{
    #[Assert\NotBlank]
    public string $project = '';

    #[Assert\NotBlank]
    public string $title = '';

    /** Markdown : objectif, résultat attendu. */
    public ?string $description = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['planned', 'in_progress', 'in_review', 'done', 'cancelled']])]
    public ?string $status = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['low', 'medium', 'high']])]
    public ?string $risk = null;
}
