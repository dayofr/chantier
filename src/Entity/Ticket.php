<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Enum\DependencyType;
use App\Filter\IsNullFilter;
use App\Enum\Priority;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Repository\TicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(fields: ['status'])]
#[ApiResource(
    operations: [
        new GetCollection(parameters: [
            'project' => new QueryParameter(filter: new ExactFilter(), property: 'project.key'),
            'epic' => new QueryParameter(filter: new ExactFilter(), property: 'epic.key'),
            'status' => new QueryParameter(filter: new ExactFilter(), property: 'status'),
            'priority' => new QueryParameter(filter: new ExactFilter(), property: 'priority'),
            'type' => new QueryParameter(filter: new ExactFilter(), property: 'type'),
            'title' => new QueryParameter(filter: new PartialSearchFilter(), property: 'title'),
            'orphan' => new QueryParameter(filter: new IsNullFilter(), property: 'epic', description: 'true : tickets sans epic.'),
        ]),
        new Get(),
        new Post(denormalizationContext: ['groups' => ['write', 'ticket:create']]),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    order: ['number' => 'ASC'],
)]
class Ticket
{
    use TimestampableTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[ApiProperty(identifier: false)]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['read'])]
    private string $key = '';

    #[ORM\Column]
    private int $number = 0;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'ticket:create'])]
    #[Assert\NotNull]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Project $project = null;

    /** Null = ticket orphelin. */
    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['read', 'write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Epic $epic = null;

    #[ORM\Column(length: 200)]
    #[Groups(['read', 'write'])]
    #[Assert\NotBlank, Assert\Length(max: 200)]
    private string $title = '';

    /** Markdown. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: TicketType::class)]
    #[Groups(['read', 'write'])]
    private TicketType $type = TicketType::Feature;

    #[ORM\Column(length: 20, enumType: TicketStatus::class)]
    #[Groups(['read', 'write'])]
    private TicketStatus $status = TicketStatus::Todo;

    #[ORM\Column(length: 10, enumType: Priority::class)]
    #[Groups(['read', 'write'])]
    private Priority $priority = Priority::Medium;

    #[ORM\Column(nullable: true)]
    #[Groups(['read', 'write'])]
    #[Assert\PositiveOrZero]
    private ?int $storyPoints = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 40)])]
    private array $labels = [];

    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $assignee = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read'])]
    private ?\DateTimeImmutable $completedAt = null;

    /** @var Collection<int, SubTask> */
    #[ORM\OneToMany(targetEntity: SubTask::class, mappedBy: 'ticket', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Groups(['read'])]
    private Collection $subTasks;

    /** Dépendances où ce ticket est la source. @var Collection<int, TicketDependency> */
    #[ORM\OneToMany(targetEntity: TicketDependency::class, mappedBy: 'source', cascade: ['remove'])]
    private Collection $outgoingDependencies;

    /** Dépendances où ce ticket est la cible. @var Collection<int, TicketDependency> */
    #[ORM\OneToMany(targetEntity: TicketDependency::class, mappedBy: 'target', cascade: ['remove'])]
    private Collection $incomingDependencies;

    /** @var Collection<int, TicketLink> */
    #[ORM\OneToMany(targetEntity: TicketLink::class, mappedBy: 'ticket', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Groups(['read'])]
    private Collection $links;

    public function __construct(?Project $project = null, string $title = '')
    {
        $this->project = $project;
        $this->title = $title;
        $this->subTasks = new ArrayCollection();
        $this->outgoingDependencies = new ArrayCollection();
        $this->incomingDependencies = new ArrayCollection();
        $this->links = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function assignKey(): void
    {
        if ('' === $this->key && null !== $this->project) {
            $this->number = $this->project->nextTicketNumber();
            $this->key = \sprintf('%s-%d', $this->project->getKey(), $this->number);
        }
    }

    #[Assert\Callback]
    public function validateEpicProject(ExecutionContextInterface $context): void
    {
        if (null !== $this->epic && $this->epic->getProject() !== $this->project) {
            $context->buildViolation('L\'epic doit appartenir au même projet que le ticket.')
                ->atPath('epic')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getEpic(): ?Epic
    {
        return $this->epic;
    }

    public function setEpic(?Epic $epic): static
    {
        $this->epic = $epic;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getType(): TicketType
    {
        return $this->type;
    }

    public function setType(TicketType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): TicketStatus
    {
        return $this->status;
    }

    /** Met aussi à jour les dates de début et de fin. */
    public function setStatus(TicketStatus $status): static
    {
        $this->status = $status;
        $now = new \DateTimeImmutable();

        if (TicketStatus::InProgress === $status) {
            $this->startedAt ??= $now;
        }
        if ($status->isClosed()) {
            $this->completedAt ??= $now;
        } else {
            $this->completedAt = null;
        }

        return $this;
    }

    public function getPriority(): Priority
    {
        return $this->priority;
    }

    public function setPriority(Priority $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getStoryPoints(): ?int
    {
        return $this->storyPoints;
    }

    public function setStoryPoints(?int $storyPoints): static
    {
        $this->storyPoints = $storyPoints;

        return $this;
    }

    /** @return list<string> */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /** @param list<string> $labels */
    public function setLabels(array $labels): static
    {
        $this->labels = array_values(array_unique(array_filter(array_map('trim', $labels))));

        return $this;
    }

    public function getAssignee(): ?string
    {
        return $this->assignee;
    }

    public function setAssignee(?string $assignee): static
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /** @return Collection<int, SubTask> */
    public function getSubTasks(): Collection
    {
        return $this->subTasks;
    }

    public function addSubTask(string $title): SubTask
    {
        $subTask = new SubTask($this, $title, \count($this->subTasks));
        $this->subTasks->add($subTask);

        return $subTask;
    }

    /** @return Collection<int, TicketDependency> */
    public function getOutgoingDependencies(): Collection
    {
        return $this->outgoingDependencies;
    }

    /** @return Collection<int, TicketDependency> */
    public function getIncomingDependencies(): Collection
    {
        return $this->incomingDependencies;
    }

    /**
     * Tickets encore ouverts qui bloquent celui-ci.
     *
     * @return list<Ticket>
     */
    #[Groups(['read'])]
    #[ApiProperty(readableLink: false)]
    public function getOpenBlockers(): array
    {
        $blockers = [];
        foreach ($this->incomingDependencies as $dependency) {
            if (DependencyType::Blocks === $dependency->getType() && !$dependency->getSource()->getStatus()->isClosed()) {
                $blockers[] = $dependency->getSource();
            }
        }

        return $blockers;
    }

    #[Groups(['read'])]
    public function isBlocked(): bool
    {
        return TicketStatus::Blocked === $this->status || [] !== $this->getOpenBlockers();
    }

    /** @return Collection<int, TicketLink> */
    public function getLinks(): Collection
    {
        return $this->links;
    }

    public function addLink(TicketLink $link): static
    {
        if (!$this->links->contains($link)) {
            $this->links->add($link);
        }

        return $this;
    }
}
