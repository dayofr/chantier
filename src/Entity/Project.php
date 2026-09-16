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
use App\Enum\ProjectStatus;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('key')]
#[ApiResource(
    operations: [
        new GetCollection(parameters: [
            'status' => new QueryParameter(filter: new ExactFilter(), property: 'status'),
            'name' => new QueryParameter(filter: new PartialSearchFilter(), property: 'name'),
        ]),
        new Get(),
        new Post(denormalizationContext: ['groups' => ['write', 'project:create']]),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    order: ['name' => 'ASC'],
)]
class Project
{
    use TimestampableTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[ApiProperty(identifier: false)]
    private ?int $id = null;

    /** Préfixe des tickets, ex. AETH → AETH-12. */
    #[ORM\Column(length: 10, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['read', 'project:create'])]
    #[Assert\NotBlank, Assert\Regex('/^[A-Z][A-Z0-9]{1,9}$/', message: 'La clé doit faire 2 à 10 caractères, majuscules et chiffres, en commençant par une lettre.')]
    private string $key = '';

    #[ORM\Column(length: 150)]
    #[Groups(['read', 'write'])]
    #[Assert\NotBlank, Assert\Length(max: 150)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: ProjectStatus::class)]
    #[Groups(['read', 'write'])]
    private ProjectStatus $status = ProjectStatus::Active;

    /** Chemin local ou URL du dépôt. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    #[Assert\Length(max: 255)]
    private ?string $repository = null;

    #[ORM\Column]
    private int $ticketSequence = 0;

    #[ORM\Column]
    private int $initiativeSequence = 0;

    #[ORM\Column]
    private int $epicSequence = 0;

    /** @var Collection<int, Initiative> */
    #[ORM\OneToMany(targetEntity: Initiative::class, mappedBy: 'project', cascade: ['remove'])]
    #[ORM\OrderBy(['position' => 'ASC', 'number' => 'ASC'])]
    private Collection $initiatives;

    /** @var Collection<int, Ticket> */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'project', cascade: ['remove'])]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $tickets;

    public function __construct(string $key = '', string $name = '')
    {
        $this->key = $key;
        $this->name = $name;
        $this->initiatives = new ArrayCollection();
        $this->tickets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = strtoupper(trim($key));

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getStatus(): ProjectStatus
    {
        return $this->status;
    }

    public function setStatus(ProjectStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRepository(): ?string
    {
        return $this->repository;
    }

    public function setRepository(?string $repository): static
    {
        $this->repository = $repository;

        return $this;
    }

    public function nextTicketNumber(): int
    {
        return ++$this->ticketSequence;
    }

    public function nextInitiativeNumber(): int
    {
        return ++$this->initiativeSequence;
    }

    public function nextEpicNumber(): int
    {
        return ++$this->epicSequence;
    }

    /** @return Collection<int, Initiative> */
    public function getInitiatives(): Collection
    {
        return $this->initiatives;
    }

    /** @return Collection<int, Ticket> */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }
}
