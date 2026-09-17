<?php

namespace App\Mcp\Tool;

use ApiPlatform\Metadata\McpTool;
use App\Mcp\Processor\CreateProjectProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'create_project',
    description: 'Crée un projet. La clé sert de préfixe aux tickets (AETH → AETH-1). Vérifie avec list_projects qu\'il n\'existe pas déjà.',
    processor: CreateProjectProcessor::class,
    structuredContent: false,
    validate: true,
)]
final class CreateProject
{
    use SessionAwareTrait;

    /** 2 à 10 caractères, majuscules et chiffres, ex. AETH. */
    #[Assert\NotBlank]
    public string $key = '';

    #[Assert\NotBlank]
    public string $name = '';

    /** Markdown : objectif, périmètre, contexte technique. */
    public ?string $description = null;

    /** Chemin local ou URL du dépôt git. */
    public ?string $repository = null;
}
