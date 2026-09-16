<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class SubTask
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'subTasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\Column(length: 255)]
    #[Groups(['read'])]
    #[Assert\NotBlank, Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column]
    #[Groups(['read'])]
    private bool $done = false;

    #[ORM\Column]
    #[Groups(['read'])]
    private int $position;

    public function __construct(Ticket $ticket, string $title, int $position = 0)
    {
        $this->ticket = $ticket;
        $this->title = $title;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function setDone(bool $done): static
    {
        $this->done = $done;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
