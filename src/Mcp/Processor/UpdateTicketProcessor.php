<?php

namespace App\Mcp\Processor;

use App\Activity\ActorContext;
use App\Entity\Activity;
use App\Enum\ActivityType;
use App\Enum\Priority;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Mcp\Tool\UpdateTicket;

/** @extends AbstractToolProcessor<UpdateTicket> */
final class UpdateTicketProcessor extends AbstractToolProcessor
{
    public function __construct(private readonly ActorContext $actor)
    {
    }

    protected function handle(object $data): array
    {
        $ticket = $this->lookup->ticket($data->ticket);

        if (null !== $status = $this->enum(TicketStatus::class, $data->status, 'status')) {
            $ticket->setStatus($status);
        }
        if (null !== $data->title) {
            $ticket->setTitle($data->title);
        }
        if (null !== $data->description) {
            $ticket->setDescription('' === $data->description ? null : $data->description);
        }
        if (null !== $priority = $this->enum(Priority::class, $data->priority, 'priority')) {
            $ticket->setPriority($priority);
        }
        if (null !== $type = $this->enum(TicketType::class, $data->type, 'type')) {
            $ticket->setType($type);
        }
        if (null !== $data->storyPoints) {
            $ticket->setStoryPoints($data->storyPoints);
        }
        if (null !== $data->labels) {
            $ticket->setLabels($data->labels);
        }
        if (null !== $data->epic) {
            $ticket->setEpic(\in_array(strtolower($data->epic), ['', 'none'], true) ? null : $this->lookup->epic($data->epic));
        }
        if (null !== $data->assignee) {
            $ticket->setAssignee('' === $data->assignee ? null : $data->assignee);
        }

        $entities = [$ticket];
        if (null !== $data->comment && '' !== trim($data->comment)) {
            $entities[] = $this->actor->stamp(new Activity($ticket->getProject(), ActivityType::Note, $data->comment)->setTicket($ticket));
        }
        $this->save(...$entities);

        return $this->presenter->ticket($ticket);
    }
}
