<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\LogActivityProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'log_activity',
    description: 'Écrit une entrée au journal, visible par l\'humain. À utiliser pour les étapes notables : décision, avancée, blocage, commit, résultat de tests. Donner ticket, ou project pour une note générale.',
    processor: LogActivityProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class LogActivity
{
    /** Clé du ticket concerné. Prioritaire sur project. */
    public ?string $ticket = null;

    /** Clé du projet, si la note ne concerne pas un ticket. */
    public ?string $project = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['note', 'decision', 'progress', 'blocker', 'commit', 'test']])]
    public string $type = 'note';

    /** Markdown, court et factuel. */
    #[Assert\NotBlank]
    public string $message = '';
}
