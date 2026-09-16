<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum TicketStatus: string implements TranslatableInterface
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

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('ticket_status.'.$this->value, locale: $locale);
    }
}
