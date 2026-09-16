<?php

namespace App\Enum;

enum TicketStatus: string
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Blocked = 'blocked';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /** Ticket fermé : ne compte plus dans le reste à faire. */
    public function isClosed(): bool
    {
        return self::Done === $this || self::Cancelled === $this;
    }
}
