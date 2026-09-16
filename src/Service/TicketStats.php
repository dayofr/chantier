<?php

namespace App\Service;

use App\Entity\Ticket;
use App\Enum\TicketStatus;

/**
 * Compteurs d'avancement sur un lot de tickets. Les tickets annulés sont ignorés.
 */
final readonly class TicketStats
{
    private function __construct(
        public int $total,
        public int $done,
        public int $inProgress,
        public int $inReview,
        public int $blocked,
        public int $points,
        public int $pointsDone,
    ) {
    }

    /** @param iterable<Ticket> $tickets */
    public static function of(iterable $tickets): self
    {
        $total = $done = $inProgress = $inReview = $blocked = $points = $pointsDone = 0;

        foreach ($tickets as $ticket) {
            $status = $ticket->getStatus();
            if (TicketStatus::Cancelled === $status) {
                continue;
            }
            ++$total;
            $points += $ticket->getStoryPoints() ?? 0;
            if (TicketStatus::Done === $status) {
                ++$done;
                $pointsDone += $ticket->getStoryPoints() ?? 0;
                continue;
            }
            match (true) {
                $ticket->isBlocked() => ++$blocked,
                TicketStatus::InProgress === $status => ++$inProgress,
                TicketStatus::InReview === $status => ++$inReview,
                default => null,
            };
        }

        return new self($total, $done, $inProgress, $inReview, $blocked, $points, $pointsDone);
    }

    public function progress(): int
    {
        return 0 === $this->total ? 0 : (int) round($this->done * 100 / $this->total);
    }

    public function remaining(): int
    {
        return $this->total - $this->done;
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'done' => $this->done,
            'remaining' => $this->remaining(),
            'inProgress' => $this->inProgress,
            'inReview' => $this->inReview,
            'blocked' => $this->blocked,
            'progress' => $this->progress(),
            'points' => $this->points,
            'pointsDone' => $this->pointsDone,
        ];
    }
}
