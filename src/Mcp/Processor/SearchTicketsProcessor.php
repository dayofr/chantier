<?php

namespace App\Mcp\Processor;

use App\Enum\Priority;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Mcp\Tool\SearchTickets;
use App\Repository\TicketRepository;

/** @extends AbstractToolProcessor<SearchTickets> */
final class SearchTicketsProcessor extends AbstractToolProcessor
{
    public function __construct(private readonly TicketRepository $tickets)
    {
    }

    protected function handle(object $data): array
    {
        $statuses = array_map(fn ($s) => $this->enum(TicketStatus::class, (string) $s, 'status'), $data->status ?? []);

        $results = $this->tickets->search(
            project: null !== $data->project ? $this->lookup->project($data->project) : null,
            epic: null !== $data->epic ? $this->lookup->epic($data->epic) : null,
            statuses: array_filter($statuses),
            priority: $this->enum(Priority::class, $data->priority, 'priority'),
            type: $this->enum(TicketType::class, $data->type, 'type'),
            label: $data->label,
            query: $data->query,
            orphan: $data->orphan,
            limit: $data->limit,
        );

        return ['count' => \count($results), 'tickets' => $this->presenter->ticketList($results)];
    }
}
