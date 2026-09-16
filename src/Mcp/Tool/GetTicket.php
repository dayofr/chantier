<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\GetTicketProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'get_ticket',
    description: 'Détail d\'un ticket : description, sous-tâches (avec id), dépendances, liens et 10 dernières activités.',
    processor: GetTicketProcessor::class,
    structuredContent: false,
    validate: true,
    annotations: ['readOnlyHint' => true],
)]
final class GetTicket
{
    /** Clé du ticket, ex. AETH-12. */
    #[Assert\NotBlank]
    public string $ticket = '';
}
