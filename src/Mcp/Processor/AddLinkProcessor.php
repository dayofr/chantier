<?php

namespace App\Mcp\Processor;

use App\Entity\TicketLink;
use App\Enum\LinkType;
use App\Mcp\Tool\AddLink;

/** @extends AbstractToolProcessor<AddLink> */
final class AddLinkProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $ticket = $this->lookup->ticket($data->ticket);
        $this->save(new TicketLink($ticket, $this->enum(LinkType::class, $data->type, 'type'), $data->reference, $data->label));

        return $this->presenter->ticketDetail($ticket);
    }
}
