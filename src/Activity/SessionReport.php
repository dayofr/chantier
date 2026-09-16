<?php

namespace App\Activity;

use App\Entity\Activity;
use App\Entity\AgentSession;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\ActivityType;
use App\Repository\ActivityRepository;

/**
 * Bilan d'une session, calculé depuis son journal : décisions, tickets créés,
 * terminés, passés en revue ou bloqués, et tous les tickets touchés.
 */
final readonly class SessionReport
{
    private const int MAX_ENTRIES = 5000;

    public function __construct(private ActivityRepository $activities)
    {
    }

    /**
     * @return array{
     *     entries: int,
     *     endedAt: \DateTimeImmutable,
     *     durationSeconds: int,
     *     decisions: list<Activity>,
     *     created: list<Ticket>,
     *     done: list<Ticket>,
     *     inReview: list<Ticket>,
     *     blocked: list<Ticket>,
     *     touched: list<Ticket>,
     *     projects: list<Project>
     * }
     */
    public function for(AgentSession $session): array
    {
        $entries = $this->activities->findBy(['sessionId' => $session->getSessionId()], ['id' => 'ASC'], self::MAX_ENTRIES);

        $groups = ['created' => [], 'done' => [], 'inReview' => [], 'blocked' => [], 'touched' => []];
        $decisions = $projects = [];
        $endedAt = $session->getLastSeenAt();

        foreach ($entries as $entry) {
            $endedAt = max($endedAt, $entry->getCreatedAt());
            if (null !== $project = $entry->getProject()) {
                $projects[$project->getKey()] = $project;
            }
            if (ActivityType::Decision === $entry->getType()) {
                $decisions[] = $entry;
            }

            $ticket = $entry->getTicket();
            if (null === $ticket) {
                continue;
            }
            $key = $ticket->getKey();
            $groups['touched'][$key] = $ticket;

            $data = $entry->getData();
            match (true) {
                ActivityType::Created === $entry->getType() => $groups['created'][$key] = $ticket,
                ActivityType::StatusChanged === $entry->getType() && 'done' === ($data['to'] ?? null) => $groups['done'][$key] = $ticket,
                ActivityType::StatusChanged === $entry->getType() && 'in_review' === ($data['to'] ?? null) => $groups['inReview'][$key] = $ticket,
                ActivityType::StatusChanged === $entry->getType() && 'blocked' === ($data['to'] ?? null) => $groups['blocked'][$key] = $ticket,
                default => null,
            };
        }

        $byNumber = static function (array $tickets): array {
            usort($tickets, static fn (Ticket $a, Ticket $b) => [$a->getProject()->getKey(), $a->getNumber()] <=> [$b->getProject()->getKey(), $b->getNumber()]);

            return $tickets;
        };

        return [
            'entries' => \count($entries),
            'endedAt' => $endedAt,
            'durationSeconds' => max(0, $endedAt->getTimestamp() - $session->getStartedAt()->getTimestamp()),
            'decisions' => $decisions,
            'created' => $byNumber(array_values($groups['created'])),
            'done' => $byNumber(array_values($groups['done'])),
            'inReview' => $byNumber(array_values($groups['inReview'])),
            'blocked' => $byNumber(array_values($groups['blocked'])),
            'touched' => $byNumber(array_values($groups['touched'])),
            'projects' => array_values($projects),
        ];
    }
}
