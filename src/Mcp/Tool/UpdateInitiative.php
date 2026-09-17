<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\UpdateInitiativeProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'update_initiative',
    description: 'Modifie une initiative. Seuls les champs fournis changent.',
    processor: UpdateInitiativeProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class UpdateInitiative
{
    use SessionAwareTrait;

    /** Clé de l'initiative, ex. AETH-I1. */
    #[Assert\NotBlank]
    public string $initiative = '';

    public ?string $title = null;

    public ?string $description = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['planned', 'in_progress', 'in_review', 'done', 'cancelled']])]
    public ?string $status = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['low', 'medium', 'high']])]
    public ?string $risk = null;

    /** Ordre d'affichage, 0 en premier. */
    public ?int $position = null;
}
