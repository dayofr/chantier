<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\AddLinkProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'add_link',
    description: 'Attache une référence à un ticket : pull request, commit, branche, fichier ou URL.',
    processor: AddLinkProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class AddLink
{
    #[Assert\NotBlank]
    public string $ticket = '';

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['pull_request', 'commit', 'branch', 'file', 'url']])]
    #[Assert\NotBlank]
    public string $type = '';

    /** URL, hash de commit, nom de branche ou chemin de fichier. */
    #[Assert\NotBlank]
    public string $reference = '';

    public ?string $label = null;
}
