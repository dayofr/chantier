<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Enum\PlanStatus;
use App\Repository\EpicRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EpicRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(parameters: [
            'project' => new QueryParameter(filter: new ExactFilter(), property: 'project.key'),
            'initiative' => new QueryParameter(filter: new ExactFilter(), property: 'initiative.key'),
            'status' => new QueryParameter(filter: new ExactFilter(), property: 'status'),
        ]),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    order: ['position' => 'ASC', 'number' => 'ASC'],
)]
class Epic
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

    /** Dénormalisé depuis l'initiative, pour filtrer simplement. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'epics')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    #[Assert\NotNull]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Initiative $initiative = null;

    #[ORM\Column(length: 200)]
    #[Groups(['read', 'write'])]
    #[Assert\NotBlank, Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: PlanStatus::class)]
    #[Groups(['read', 'write'])]
    private PlanStatus $status = PlanStatus::Planned;

    #[ORM\Column(nullable: true)]
    #[Groups(['read', 'write'])]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read', 'write'])]
    private ?\DateTimeImmutable $targetDate = null;

    #[ORM\Column]
    #[Groups(['read', 'write'])]
    private int $position = 0;

    /** @var Collection<int, Ticket> */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'epic')]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $tickets;

    public function __construct(?Initiative $initiative = null, string $title = '')
    {
        $this->setInitiative($initiative);
        $this->title = $title;
        $this->tickets = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function assignKey(): void
    {
        if ('' === $this->key && null !== $this->project) {
            $this->number = $this->project->nextEpicNumber();
            $this->key = \sprintf('%s-E%d', $this->project->getKey(), $this->number);
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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function getInitiative(): ?Initiative
    {
        return $this->initiative;
    }

    public function setInitiative(?Initiative $initiative): static
    {
        $this->initiative = $initiative;
        $this->project = $initiative?->getProject();

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

    public function getStatus(): PlanStatus
    {
        return $this->status;
    }

    public function setStatus(PlanStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getTargetDate(): ?\DateTimeImmutable
    {
        return $this->targetDate;
    }

    public function setTargetDate(?\DateTimeImmutable $targetDate): static
    {
        $this->targetDate = $targetDate;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /** @return Collection<int, Ticket> */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }
}
