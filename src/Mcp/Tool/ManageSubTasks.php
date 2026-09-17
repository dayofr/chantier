<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\ManageSubTasksProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'manage_subtasks',
    description: 'Ajoute, coche, décoche ou supprime des sous-tâches d\'un ticket. Les id viennent de get_ticket.',
    processor: ManageSubTasksProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class ManageSubTasks
{
    use SessionAwareTrait;

    #[Assert\NotBlank]
    public string $ticket = '';

    /** Titres des sous-tâches à ajouter. */
    #[ApiProperty(schema: ['type' => 'array', 'items' => ['type' => 'string']])]
    public array $add = [];

    /** Id des sous-tâches à cocher. */
    #[ApiProperty(schema: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $complete = [];

    /** Id des sous-tâches à décocher. */
    #[ApiProperty(schema: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $reopen = [];

    /** Id des sous-tâches à supprimer. */
    #[ApiProperty(schema: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $remove = [];
}
