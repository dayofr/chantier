<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\SaveSessionSummaryProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'save_session_summary',
    description: 'Enregistre le résumé de la séance en cours : ce qui a été demandé, discuté et fait dans la conversation, et les décisions prises. Remplace le résumé précédent : envoyer à chaque fois le résumé complet. Les décisions deviennent des entrées "decision" du journal (sans doublon).',
    processor: SaveSessionSummaryProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class SaveSessionSummary
{
    use SessionAwareTrait;

    /** Markdown. Demandes de l'utilisateur, ce qui a été fait, points ouverts. Complet, pas un delta. */
    #[Assert\NotBlank, Assert\Length(max: 20000)]
    public string $summary = '';

    #[ApiProperty(schema: [
        'type' => 'array',
        'description' => 'Décisions prises pendant la séance, avec le ticket concerné si possible.',
        'items' => [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
                'text' => ['type' => 'string', 'description' => 'La décision et sa raison, en une ou deux phrases.'],
                'ticket' => ['type' => 'string', 'description' => 'Clé du ticket concerné.'],
            ],
        ],
    ])]
    #[Assert\Count(max: 50)]
    public array $decisions = [];

    /** Projet des décisions sans ticket. */
    public ?string $project = null;
}
