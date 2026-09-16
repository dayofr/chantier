<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Enum\ActivityType;
use App\Repository\ActivityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Journal : ce que l'agent a fait, quand, dans quelle session.
 * Les entrées "created" et "status_changed" sont écrites automatiquement.
 */
#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Index(fields: ['createdAt'])]
#[ApiResource(
    operations: [
        new GetCollection(parameters: [
            'project' => new QueryParameter(filter: new ExactFilter(), property: 'project.key'),
            'ticket' => new QueryParameter(filter: new ExactFilter(), property: 'ticket.key'),
            'type' => new QueryParameter(filter: new ExactFilter(), property: 'type'),
            'sessionId' => new QueryParameter(filter: new ExactFilter(), property: 'sessionId'),
        ]),
        new Get(),
        new Post(),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    order: ['createdAt' => 'DESC', 'id' => 'DESC'],
)]
class Activity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    #[Assert\NotNull]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Ticket $ticket = null;

    /** Clé de l'élément concerné (projet, initiative, epic ou ticket). */
    #[ORM\Column(length: 20)]
    #[Groups(['read'])]
    private string $subjectKey = '';

    #[ORM\Column(length: 20, enumType: ActivityType::class)]
    #[Groups(['read', 'write'])]
    private ActivityType $type = ActivityType::Note;

    /** Markdown, écrit par l'agent. Null pour les événements automatiques. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $message = null;

    /** Données structurées, ex. {"from": "todo", "to": "in_progress"}. */
    #[ORM\Column(type: 'json')]
    #[Groups(['read'])]
    private array $data = [];

    #[ORM\Column(length: 80)]
    #[Groups(['read', 'write'])]
    private string $author = 'api';

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $sessionId = null;

    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(?Project $project = null, ActivityType $type = ActivityType::Note, ?string $message = null)
    {
        $this->project = $project;
        $this->subjectKey = $project?->getKey() ?? '';
        $this->type = $type;
        $this->message = $message;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
        if (null === $this->ticket) {
            $this->subjectKey = $project?->getKey() ?? '';
        }

        return $this;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): static
    {
        $this->ticket = $ticket;
        if (null !== $ticket) {
            $this->project = $ticket->getProject();
            $this->subjectKey = $ticket->getKey();
        }

        return $this;
    }

    public function getSubjectKey(): string
    {
        return $this->subjectKey;
    }

    public function setSubjectKey(string $subjectKey): static
    {
        $this->subjectKey = $subjectKey;

        return $this;
    }

    public function getType(): ActivityType
    {
        return $this->type;
    }

    public function setType(ActivityType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
