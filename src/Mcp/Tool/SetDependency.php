<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\SetDependencyProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'set_dependency',
    description: 'Crée ou supprime un lien entre deux tickets. type=blocks : "source" bloque "target" tant que source n\'est pas terminé.',
    processor: SetDependencyProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class SetDependency
{
    use SessionAwareTrait;

    /** Ticket bloquant, ex. AETH-3. */
    #[Assert\NotBlank]
    public string $source = '';

    /** Ticket bloqué, ex. AETH-7. */
    #[Assert\NotBlank]
    public string $target = '';

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['blocks', 'relates_to']])]
    public string $type = 'blocks';

    /** true pour supprimer le lien. */
    public bool $remove = false;
}
