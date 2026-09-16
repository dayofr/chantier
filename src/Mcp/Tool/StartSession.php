<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\StartSessionProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'start_session',
    description: 'Nomme la séance de travail en cours (à appeler au début, puis si l\'objectif change). Renvoie aussi les résumés des dernières séances pour reprendre le contexte.',
    processor: StartSessionProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class StartSession
{
    /** Objectif de la séance en quelques mots, ex. "CHANT-39 à 42 : sessions et résumés". */
    #[Assert\NotBlank, Assert\Length(max: 200)]
    public string $title = '';

    /** Branche git de travail. */
    #[Assert\Length(max: 200)]
    public ?string $branch = null;
}
