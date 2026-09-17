<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\RemoveLinkProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'remove_link',
    description: 'Retire une référence d\'un ticket (lien posé par erreur ou obsolète). Le retrait est noté au journal avec la raison.',
    processor: RemoveLinkProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class RemoveLink
{
    use SessionAwareTrait;

    #[Assert\NotBlank]
    public string $ticket = '';

    /** Référence exacte du lien, telle que renvoyée par get_ticket (URL, hash, branche, chemin). */
    #[Assert\NotBlank]
    public string $reference = '';

    /** Type du lien, pour lever l'ambiguïté si la même référence existe sous plusieurs types. */
    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['pull_request', 'commit', 'branch', 'file', 'url']])]
    public ?string $type = null;

    /** Pourquoi le lien est retiré, ex. "posé sur le mauvais ticket". */
    public ?string $reason = null;
}
