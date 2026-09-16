<?php

namespace App\Entity;

use App\Repository\AgentSessionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Séance de travail d'un agent : une connexion MCP.
 * Les entrées du journal s'y rattachent par sessionId.
 */
#[ORM\Entity(repositoryClass: AgentSessionRepository::class)]
#[ORM\Index(fields: ['lastSeenAt'])]
class AgentSession
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Identifiant de session MCP. */
    #[ORM\Column(length: 100, unique: true)]
    private string $sessionId;

    /** Nom du client MCP, ex. claude-code. */
    #[ORM\Column(length: 80)]
    private string $client;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $title = null;

    /** Résumé de la conversation, en markdown, écrit par l'agent. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $summaryUpdatedAt = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $branch = null;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct(string $sessionId, string $client, ?\DateTimeImmutable $at = null)
    {
        $this->sessionId = $sessionId;
        $this->client = $client;
        $this->startedAt = $at ?? new \DateTimeImmutable();
        $this->lastSeenAt = $this->startedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getShortId(): string
    {
        return substr($this->sessionId, 0, 8);
    }

    public function getClient(): string
    {
        return $this->client;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = null === $title || '' === trim($title) ? null : trim($title);

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary, ?\DateTimeImmutable $at = null): static
    {
        $this->summary = null === $summary || '' === trim($summary) ? null : $summary;
        $this->summaryUpdatedAt = null === $this->summary ? null : ($at ?? new \DateTimeImmutable());

        return $this;
    }

    public function getSummaryUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->summaryUpdatedAt;
    }

    public function getBranch(): ?string
    {
        return $this->branch;
    }

    public function setBranch(?string $branch): static
    {
        $this->branch = null === $branch || '' === trim($branch) ? null : trim($branch);

        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function seenAt(\DateTimeImmutable $at): static
    {
        if ($at > $this->lastSeenAt) {
            $this->lastSeenAt = $at;
        }
        if ($at < $this->startedAt) {
            $this->startedAt = $at;
        }

        return $this;
    }

    /** Titre ou, à défaut, identifiant court. */
    public function getLabel(): string
    {
        return $this->title ?? '#'.$this->getShortId();
    }
}
