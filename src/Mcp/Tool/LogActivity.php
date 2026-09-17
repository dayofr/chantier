<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\LogActivityProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'log_activity',
    description: 'Écrit une entrée au journal, visible par l\'humain. À utiliser pour les étapes notables : décision, avancée, blocage, commit, résultat de tests. Donner subject (ticket, epic ou initiative), ou project pour une note générale. Une décision qui concerne tout un epic ou une initiative se pose sur sa clé.',
    processor: LogActivityProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class LogActivity
{
    use SessionAwareTrait;

    /** Clé du ticket (CHANT-12), de l'epic (CHANT-E3) ou de l'initiative (CHANT-I1) concerné. Prioritaire sur project. */
    public ?string $subject = null;

    /** Ancien nom de subject, accepté pour compatibilité. */
    public ?string $ticket = null;

    /** Clé du projet, si la note ne concerne pas un ticket. */
    public ?string $project = null;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => ['note', 'decision', 'progress', 'blocker', 'commit', 'test']])]
    public string $type = 'note';

    /** Markdown, court et factuel. */
    #[Assert\NotBlank]
    public string $message = '';
}
