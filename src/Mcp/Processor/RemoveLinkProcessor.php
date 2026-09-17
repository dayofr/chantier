<?php

namespace App\Mcp\Processor;

use App\Activity\ActorContext;
use App\Entity\Activity;
use App\Entity\TicketLink;
use App\Enum\ActivityType;
use App\Enum\LinkType;
use App\Mcp\ToolError;
use App\Mcp\Tool\RemoveLink;

/** @extends AbstractToolProcessor<RemoveLink> */
final class RemoveLinkProcessor extends AbstractToolProcessor
{
    public function __construct(private readonly ActorContext $actor)
    {
    }

    protected function handle(object $data): array
    {
        $ticket = $this->lookup->ticket($data->ticket);
        $type = $this->enum(LinkType::class, $data->type, 'type');
        $reference = trim($data->reference);

        $matches = $ticket->getLinks()->filter(static fn (TicketLink $l) => $l->getReference() === $reference && (null === $type || $l->getType() === $type))->getValues();

        if ([] === $matches) {
            $existing = $ticket->getLinks()->map(static fn (TicketLink $l) => $l->getType()->value.' '.$l->getReference())->getValues();
            throw new ToolError(\sprintf('Aucun lien "%s" sur %s. Liens existants : %s.', $reference, $ticket->getKey(), implode(', ', $existing) ?: 'aucun'));
        }
        if (\count($matches) > 1) {
            throw new ToolError(\sprintf('Plusieurs liens "%s" sur %s (%s) : préciser type.', $reference, $ticket->getKey(), implode(', ', array_map(static fn (TicketLink $l) => $l->getType()->value, $matches))));
        }

        $link = $matches[0];
        $ticket->getLinks()->removeElement($link);

        $message = \sprintf('Lien retiré : %s `%s`', $link->getType()->value, $link->getReference());
        if (null !== $data->reason && '' !== trim($data->reason)) {
            $message .= ' — '.trim($data->reason);
        }
        $this->save($ticket, $this->actor->stamp(new Activity($ticket->getProject(), ActivityType::Note, $message)->setTicket($ticket)));

        return $this->presenter->ticketDetail($ticket);
    }
}
