<?php

namespace App\Mcp\Processor;

use App\Mcp\Tool\GetTicket;

/** @extends AbstractToolProcessor<GetTicket> */
final class GetTicketProcessor extends AbstractToolProcessor
{
    protected const bool READ_ONLY = true;

    protected function handle(object $data): array
    {
        return $this->presenter->ticketDetail($this->lookup->ticket($data->ticket));
    }
}
