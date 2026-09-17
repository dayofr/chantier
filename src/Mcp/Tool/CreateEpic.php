<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\CreateEpicProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'create_epic',
    description: 'Crée un epic (lot livrable) dans une initiative. Il regroupe des tickets.',
    processor: CreateEpicProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class CreateEpic
{
    use SessionAwareTrait;

    /** Clé de l'initiative parente, ex. AETH-I1. */
    #[Assert\NotBlank]
    public string $initiative = '';

    #[Assert\NotBlank]
    public string $title = '';

    /** Markdown. */
    public ?string $description = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['planned', 'in_progress', 'in_review', 'done', 'cancelled']])]
    public ?string $status = null;

    /** AAAA-MM-JJ. */
    public ?string $startDate = null;

    /** AAAA-MM-JJ. */
    public ?string $targetDate = null;
}
