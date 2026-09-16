<?php

namespace App\Mcp\Processor;

use App\Enum\TicketStatus;
use App\Mcp\Tool\GetNextTicket;
use App\Repository\TicketRepository;

/** @extends AbstractToolProcessor<GetNextTicket> */
final class GetNextTicketProcessor extends AbstractToolProcessor
{
    public function __construct(private readonly TicketRepository $tickets)
    {
    }

    protected function handle(object $data): array
    {
        $project = $this->lookup->project($data->project);
        $epic = null !== $data->epic ? $this->lookup->epic($data->epic) : null;

        foreach ([TicketStatus::InProgress, TicketStatus::Todo, TicketStatus::Backlog] as $status) {
            foreach ($this->tickets->search(project: $project, epic: $epic, statuses: [$status], limit: 500) as $ticket) {
                if (!$ticket->isBlocked()) {
                    return ['ticket' => $this->presenter->ticketDetail($ticket)];
                }
            }
        }

        return ['ticket' => null, 'message' => 'Aucun ticket actionnable : tout est terminé, bloqué ou en revue.'];
    }
}
