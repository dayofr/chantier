<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\GetNextTicketProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'get_next_ticket',
    description: 'Prochain ticket à traiter : d\'abord un ticket déjà in_progress, sinon le todo non bloqué le plus prioritaire (puis backlog). Renvoie le détail complet.',
    processor: GetNextTicketProcessor::class,
    structuredContent: false,
    validate: true,
    annotations: ['readOnlyHint' => true],
)]
final class GetNextTicket
{
    #[Assert\NotBlank]
    public string $project = '';

    /** Limiter à un epic. */
    public ?string $epic = null;
}
