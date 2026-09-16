<?php

namespace App\Mcp\Processor;

use App\Entity\Epic;
use App\Entity\Ticket;
use App\Entity\TicketDependency;
use App\Enum\Priority;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Mcp\ToolError;
use App\Mcp\Tool\CreateTickets;

/** @extends AbstractToolProcessor<CreateTickets> */
final class CreateTicketsProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $project = $this->lookup->project($data->project);
        $defaultEpic = null !== $data->epic && '' !== $data->epic ? $this->lookup->epic($data->epic) : null;

        return $this->em->wrapInTransaction(function () use ($project, $defaultEpic, $data): array {
            /** @var list<Ticket> $created */
            $created = [];
            foreach (array_values($data->tickets) as $i => $input) {
                $input = (array) $input;
                $field = static fn (string $name) => \sprintf('tickets[%d].%s', $i, $name);
                $ticket = new Ticket($project, (string) ($input['title'] ?? ''))
                    ->setDescription($input['description'] ?? null)
                    ->setType($this->enum(TicketType::class, $input['type'] ?? null, $field('type')) ?? TicketType::Feature)
                    ->setStatus($this->enum(TicketStatus::class, $input['status'] ?? null, $field('status')) ?? TicketStatus::Todo)
                    ->setPriority($this->enum(Priority::class, $input['priority'] ?? null, $field('priority')) ?? Priority::Medium)
                    ->setStoryPoints(isset($input['storyPoints']) ? (int) $input['storyPoints'] : null)
                    ->setLabels((array) ($input['labels'] ?? []))
                    ->setEpic($this->epicFor($input['epic'] ?? null, $defaultEpic));
                foreach ((array) ($input['subTasks'] ?? []) as $title) {
                    $ticket->addSubTask((string) $title);
                }
                $this->save($ticket);
                $created[] = $ticket;
            }

            $dependencies = [];
            foreach (array_values($data->tickets) as $i => $input) {
                foreach ((array) (((array) $input)['blockedBy'] ?? []) as $ref) {
                    $blocker = $this->resolve((string) $ref, $created);
                    $dependencies[] = new TicketDependency($blocker, $created[$i]);
                }
            }
            if ([] !== $dependencies) {
                $this->save(...$dependencies);
            }

            return ['created' => $this->presenter->ticketList($created)];
        });
    }

    private function epicFor(?string $key, ?Epic $default): ?Epic
    {
        return match (true) {
            null === $key || '' === $key => $default,
            'none' === strtolower($key) => null,
            default => $this->lookup->epic($key),
        };
    }

    /** @param list<Ticket> $batch */
    private function resolve(string $ref, array $batch): Ticket
    {
        if (preg_match('/^#(\d+)$/', trim($ref), $m)) {
            return $batch[(int) $m[1]] ?? throw new ToolError(\sprintf('blockedBy : index "%s" hors du lot.', $ref));
        }

        return $this->lookup->ticket($ref);
    }
}
