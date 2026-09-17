<?php

namespace App\Service;

use App\Entity\Epic;
use App\Entity\Ticket;
use App\Enum\TicketStatus;

/** Colonnes de Kanban pour un lot de tickets (projet ou initiative). */
final class KanbanBoard
{
    /** Cartes visibles dans la colonne Terminé avant le repli des plus anciennes. */
    public const int DONE_VISIBLE = 10;

    /** Colonnes, dans l'ordre. Les tickets annulés sont masqués. */
    public const array STATUSES = [
        TicketStatus::Backlog,
        TicketStatus::Todo,
        TicketStatus::InProgress,
        TicketStatus::InReview,
        TicketStatus::Blocked,
        TicketStatus::Done,
    ];

    /**
     * @param iterable<Ticket> $tickets
     *
     * @return array<string, list<Ticket>> tickets par statut
     */
    public function columns(iterable $tickets): array
    {
        $columns = array_fill_keys(array_map(static fn (TicketStatus $s) => $s->value, self::STATUSES), []);
        foreach ($tickets as $ticket) {
            if (isset($columns[$ticket->getStatus()->value])) {
                $columns[$ticket->getStatus()->value][] = $ticket;
            }
        }

        foreach ($columns as $status => &$column) {
            usort($column, TicketStatus::Done->value === $status
                // Terminés : les plus récemment finis d'abord.
                ? static fn (Ticket $a, Ticket $b) => [$b->getCompletedAt(), $b->getNumber()] <=> [$a->getCompletedAt(), $a->getNumber()]
                : static fn (Ticket $a, Ticket $b) => [$b->getPriority()->weight(), $a->getNumber()] <=> [$a->getPriority()->weight(), $b->getNumber()]);
        }

        return $columns;
    }

    /**
     * Filtre par epic : clé d'epic, "none" pour les tickets sans epic, null pour tout.
     *
     * @param iterable<Ticket> $tickets
     *
     * @return list<Ticket>
     */
    public function filterByEpic(iterable $tickets, ?string $epic): array
    {
        $result = [];
        foreach ($tickets as $ticket) {
            $keep = match (strtoupper((string) $epic)) {
                '' => true,
                'NONE' => null === $ticket->getEpic(),
                default => $ticket->getEpic()?->getKey() === strtoupper($epic),
            };
            if ($keep) {
                $result[] = $ticket;
            }
        }

        return $result;
    }

    /**
     * Nombre de tickets (hors annulés) par clé d'epic, "NONE" pour les tickets sans epic.
     *
     * @param iterable<Ticket> $tickets
     *
     * @return array<string, int>
     */
    public function epicCounts(iterable $tickets): array
    {
        $counts = [];
        foreach ($tickets as $ticket) {
            if (TicketStatus::Cancelled !== $ticket->getStatus()) {
                $key = $ticket->getEpic()?->getKey() ?? 'NONE';
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
