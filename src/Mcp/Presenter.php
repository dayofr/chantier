<?php

namespace App\Mcp;

use App\Entity\Activity;
use App\Entity\Epic;
use App\Entity\Initiative;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\DependencyType;
use App\Enum\TicketStatus;
use App\Repository\ActivityRepository;
use App\Service\TicketStats;

/**
 * Représentations compactes renvoyées aux agents.
 */
final readonly class Presenter
{
    public function __construct(private ActivityRepository $activities)
    {
    }

    public function project(Project $project): array
    {
        return [
            'key' => $project->getKey(),
            'name' => $project->getName(),
            'status' => $project->getStatus()->value,
            'description' => $project->getDescription(),
            'repository' => $project->getRepository(),
            'stats' => TicketStats::of($project->getTickets())->toArray(),
        ];
    }

    public function projectTree(Project $project, bool $includeClosed): array
    {
        $orphans = $project->getTickets()->filter(static fn (Ticket $t) => null === $t->getEpic());

        return $this->project($project) + [
            'initiatives' => $project->getInitiatives()->map(fn (Initiative $i) => $this->initiative($i) + [
                'epics' => $i->getEpics()->map(fn (Epic $e) => $this->epic($e) + [
                    'tickets' => $this->ticketList($e->getTickets(), $includeClosed),
                ])->getValues(),
            ])->getValues(),
            'orphanTickets' => $this->ticketList($orphans, $includeClosed),
        ];
    }

    public function initiative(Initiative $initiative): array
    {
        $tickets = [];
        foreach ($initiative->getEpics() as $epic) {
            array_push($tickets, ...$epic->getTickets());
        }

        return [
            'key' => $initiative->getKey(),
            'title' => $initiative->getTitle(),
            'status' => $initiative->getStatus()->value,
            'risk' => $initiative->getRisk()->value,
            'description' => $initiative->getDescription(),
            'stats' => TicketStats::of($tickets)->toArray(),
        ];
    }

    public function epic(Epic $epic): array
    {
        return [
            'key' => $epic->getKey(),
            'initiative' => $epic->getInitiative()?->getKey(),
            'title' => $epic->getTitle(),
            'status' => $epic->getStatus()->value,
            'description' => $epic->getDescription(),
            'startDate' => $epic->getStartDate()?->format('Y-m-d'),
            'targetDate' => $epic->getTargetDate()?->format('Y-m-d'),
            'stats' => TicketStats::of($epic->getTickets())->toArray(),
        ];
    }

    /** @param iterable<Ticket> $tickets */
    public function ticketList(iterable $tickets, bool $includeClosed = true): array
    {
        $list = [];
        foreach ($tickets as $ticket) {
            if ($includeClosed || !$ticket->getStatus()->isClosed()) {
                $list[] = $this->ticket($ticket);
            }
        }

        return $list;
    }

    public function ticket(Ticket $ticket): array
    {
        $subTasks = $ticket->getSubTasks();
        $done = $subTasks->filter(static fn ($s) => $s->isDone())->count();

        return array_filter([
            'key' => $ticket->getKey(),
            'title' => $ticket->getTitle(),
            'status' => $ticket->getStatus()->value,
            'priority' => $ticket->getPriority()->value,
            'type' => $ticket->getType()->value,
            'epic' => $ticket->getEpic()?->getKey(),
            'storyPoints' => $ticket->getStoryPoints(),
            'blocked' => $ticket->isBlocked() && TicketStatus::Blocked !== $ticket->getStatus() ? true : null,
            'blockedBy' => array_map(static fn (Ticket $t) => $t->getKey(), $ticket->getOpenBlockers()) ?: null,
            'subTasks' => \count($subTasks) > 0 ? \sprintf('%d/%d', $done, \count($subTasks)) : null,
        ], static fn ($v) => null !== $v);
    }

    public function ticketDetail(Ticket $ticket): array
    {
        $blockedBy = $blocks = $related = [];
        foreach ($ticket->getIncomingDependencies() as $dep) {
            $ref = $this->ref($dep->getSource());
            DependencyType::Blocks === $dep->getType() ? $blockedBy[] = $ref : $related[] = $ref;
        }
        foreach ($ticket->getOutgoingDependencies() as $dep) {
            $ref = $this->ref($dep->getTarget());
            DependencyType::Blocks === $dep->getType() ? $blocks[] = $ref : $related[] = $ref;
        }

        return [
            'key' => $ticket->getKey(),
            'project' => $ticket->getProject()->getKey(),
            'epic' => $ticket->getEpic() ? $this->ref($ticket->getEpic()) : null,
            'initiative' => $ticket->getEpic()?->getInitiative() ? $this->ref($ticket->getEpic()->getInitiative()) : null,
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'type' => $ticket->getType()->value,
            'status' => $ticket->getStatus()->value,
            'blocked' => $ticket->isBlocked(),
            'priority' => $ticket->getPriority()->value,
            'storyPoints' => $ticket->getStoryPoints(),
            'labels' => $ticket->getLabels(),
            'assignee' => $ticket->getAssignee(),
            'startedAt' => $ticket->getStartedAt()?->format(\DATE_ATOM),
            'completedAt' => $ticket->getCompletedAt()?->format(\DATE_ATOM),
            'subTasks' => $ticket->getSubTasks()->map(static fn ($s) => ['id' => $s->getId(), 'title' => $s->getTitle(), 'done' => $s->isDone()])->getValues(),
            'blockedBy' => $blockedBy,
            'blocks' => $blocks,
            'relatedTo' => $related,
            'links' => $ticket->getLinks()->map(static fn ($l) => array_filter(['type' => $l->getType()->value, 'reference' => $l->getReference(), 'label' => $l->getLabel()]))->getValues(),
            'recentActivity' => array_map($this->activity(...), $this->activities->findBy(['ticket' => $ticket], ['createdAt' => 'DESC', 'id' => 'DESC'], 10)),
        ];
    }

    public function activity(Activity $activity): array
    {
        return array_filter([
            'id' => $activity->getId(),
            'at' => $activity->getCreatedAt()->format(\DATE_ATOM),
            'subject' => $activity->getSubjectKey(),
            'type' => $activity->getType()->value,
            'message' => $activity->getMessage(),
            'data' => $activity->getData() ?: null,
            'author' => $activity->getAuthor(),
            'session' => $activity->getSessionId(),
        ], static fn ($v) => null !== $v);
    }

    private function ref(Ticket|Epic|Initiative $item): array
    {
        return ['key' => $item->getKey(), 'title' => $item->getTitle(), 'status' => $item->getStatus()->value];
    }
}
