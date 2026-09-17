<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\UpdateEpicProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'update_epic',
    description: 'Modifie un epic. Seuls les champs fournis changent. Chaîne vide pour effacer une date.',
    processor: UpdateEpicProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class UpdateEpic
{
    use SessionAwareTrait;

    /** Clé de l'epic, ex. AETH-E3. */
    #[Assert\NotBlank]
    public string $epic = '';

    public ?string $title = null;

    public ?string $description = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['planned', 'in_progress', 'in_review', 'done', 'cancelled']])]
    public ?string $status = null;

    /** AAAA-MM-JJ. */
    public ?string $startDate = null;

    /** AAAA-MM-JJ. */
    public ?string $targetDate = null;

    /** Déplacer vers une autre initiative du même projet. */
    public ?string $initiative = null;

    public ?int $position = null;
}
