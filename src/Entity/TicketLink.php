<?php

namespace App\Entity;

use App\Enum\LinkType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Référence externe : PR, commit, branche, fichier, URL. */
#[ORM\Entity]
class TicketLink
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'links')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\Column(length: 20, enumType: LinkType::class)]
    #[Groups(['read'])]
    private LinkType $type;

    /** URL, hash, nom de branche ou chemin. */
    #[ORM\Column(length: 500)]
    #[Groups(['read'])]
    #[Assert\NotBlank, Assert\Length(max: 500)]
    private string $reference;

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['read'])]
    #[Assert\Length(max: 200)]
    private ?string $label;

    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(Ticket $ticket, LinkType $type, string $reference, ?string $label = null)
    {
        $this->ticket = $ticket;
        $this->type = $type;
        $this->reference = $reference;
        $this->label = $label;
        $this->createdAt = new \DateTimeImmutable();
        $ticket->addLink($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function getType(): LinkType
    {
        return $this->type;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
