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
use App\Enum\Risk;
use App\Repository\InitiativeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InitiativeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(parameters: [
            'project' => new QueryParameter(filter: new ExactFilter(), property: 'project.key'),
            'status' => new QueryParameter(filter: new ExactFilter(), property: 'status'),
        ]),
        new Get(),
        new Post(denormalizationContext: ['groups' => ['write', 'initiative:create']]),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    order: ['position' => 'ASC', 'number' => 'ASC'],
)]
class Initiative
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

    #[ORM\ManyToOne(inversedBy: 'initiatives')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'initiative:create'])]
    #[Assert\NotNull]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Project $project = null;

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

    #[ORM\Column(length: 10, enumType: Risk::class)]
    #[Groups(['read', 'write'])]
    private Risk $risk = Risk::Low;

    #[ORM\Column]
    #[Groups(['read', 'write'])]
    private int $position = 0;

    /** @var Collection<int, Epic> */
    #[ORM\OneToMany(targetEntity: Epic::class, mappedBy: 'initiative', cascade: ['remove'])]
    #[ORM\OrderBy(['position' => 'ASC', 'number' => 'ASC'])]
    private Collection $epics;

    public function __construct(?Project $project = null, string $title = '')
    {
        $this->project = $project;
        $this->title = $title;
        $this->epics = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function assignKey(): void
    {
        if ('' === $this->key && null !== $this->project) {
            $this->number = $this->project->nextInitiativeNumber();
            $this->key = \sprintf('%s-I%d', $this->project->getKey(), $this->number);
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

    public function setProject(?Project $project): static
    {
        $this->project = $project;

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

    public function getRisk(): Risk
    {
        return $this->risk;
    }

    public function setRisk(Risk $risk): static
    {
        $this->risk = $risk;

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

    /** @return Collection<int, Epic> */
    public function getEpics(): Collection
    {
        return $this->epics;
    }
}
