<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\DependencyType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\UniqueConstraint(fields: ['source', 'target', 'type'])]
#[UniqueEntity(fields: ['source', 'target', 'type'], message: 'Cette dépendance existe déjà.')]
#[ApiResource(
    operations: [new GetCollection(), new Get(), new Post(), new Delete()],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
)]
class TicketDependency
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'outgoingDependencies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    #[Assert\NotNull]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Ticket $source = null;

    #[ORM\ManyToOne(inversedBy: 'incomingDependencies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    #[Assert\NotNull]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Ticket $target = null;

    #[ORM\Column(length: 20, enumType: DependencyType::class)]
    #[Groups(['read', 'write'])]
    private DependencyType $type = DependencyType::Blocks;

    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(?Ticket $source = null, ?Ticket $target = null, DependencyType $type = DependencyType::Blocks)
    {
        $this->source = $source;
        $this->target = $target;
        $this->type = $type;
        $this->createdAt = new \DateTimeImmutable();
        $source?->getOutgoingDependencies()->add($this);
        $target?->getIncomingDependencies()->add($this);
    }

    #[Assert\Callback]
    public function validateTickets(ExecutionContextInterface $context): void
    {
        if (null !== $this->source && $this->source === $this->target) {
            $context->buildViolation('Un ticket ne peut pas dépendre de lui-même.')->atPath('target')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): Ticket
    {
        return $this->source;
    }

    public function setSource(?Ticket $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getTarget(): Ticket
    {
        return $this->target;
    }

    public function setTarget(?Ticket $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getType(): DependencyType
    {
        return $this->type;
    }

    public function setType(DependencyType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
