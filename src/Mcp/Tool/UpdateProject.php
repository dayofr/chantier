<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\UpdateProjectProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'update_project',
    description: 'Modifie un projet. Seuls les champs fournis changent.',
    processor: UpdateProjectProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class UpdateProject
{
    #[Assert\NotBlank]
    public string $project = '';

    public ?string $name = null;

    public ?string $description = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['planning', 'active', 'paused', 'done', 'archived']])]
    public ?string $status = null;

    public ?string $repository = null;
}
