<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\DependencyType;
use App\Enum\TicketStatus;
use App\Repository\ActivityRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Points d'attention calculés sur un projet, du plus grave au moins grave :
 * - bottleneck : ticket ouvert qui bloque au moins 2 tickets ouverts ;
 * - stale : ticket en cours ou en revue sans mouvement depuis le seuil ;
 * - blocked : ticket ouvert bloqué (statut ou bloquant ouvert) ;
 * - orphan : ticket ouvert sans epic.
 */
final readonly class ProjectAlerts
{
    public const int BOTTLENECK_MIN = 2;

    public function __construct(
        private ActivityRepository $activities,
        private ClockInterface $clock,
        #[Autowire(env: 'int:APP_STALE_HOURS')]
        private int $staleHours = 48,
    ) {
    }

    /**
     * @return list<array{type: string, severity: string, ticket: Ticket, blocks?: list<Ticket>, blockers?: list<Ticket>, idleHours?: int, lastMoveAt?: \DateTimeImmutable}>
     */
    public function for(Project $project): array
    {
        $now = $this->clock->now();
        $lastActivity = $this->activities->lastActivityByTicket($project);
        $alerts = [];

        foreach ($project->getTickets() as $ticket) {
            $status = $ticket->getStatus();
            if ($status->isClosed()) {
                continue;
            }

            $blocks = [];
            foreach ($ticket->getOutgoingDependencies() as $dep) {
                if (DependencyType::Blocks === $dep->getType() && !$dep->getTarget()->getStatus()->isClosed()) {
                    $blocks[] = $dep->getTarget();
                }
            }
            if (\count($blocks) >= self::BOTTLENECK_MIN) {
                $alerts[] = ['type' => 'bottleneck', 'severity' => \count($blocks) > self::BOTTLENECK_MIN ? 'high' : 'medium', 'ticket' => $ticket, 'blocks' => $blocks];
            }

            if (\in_array($status, [TicketStatus::InProgress, TicketStatus::InReview], true)) {
                $lastMove = max($ticket->getUpdatedAt(), $lastActivity[$ticket->getId()] ?? $ticket->getUpdatedAt());
                $idleHours = intdiv($now->getTimestamp() - $lastMove->getTimestamp(), 3600);
                if ($idleHours >= $this->staleHours) {
                    $alerts[] = ['type' => 'stale', 'severity' => $idleHours >= 3 * $this->staleHours ? 'high' : 'medium', 'ticket' => $ticket, 'idleHours' => $idleHours, 'lastMoveAt' => $lastMove];
                }
            }

            if ($ticket->isBlocked()) {
                $alerts[] = ['type' => 'blocked', 'severity' => TicketStatus::Blocked === $status && [] === $ticket->getOpenBlockers() ? 'high' : 'low', 'ticket' => $ticket, 'blockers' => $ticket->getOpenBlockers()];
            }

            if (null === $ticket->getEpic()) {
                $alerts[] = ['type' => 'orphan', 'severity' => 'low', 'ticket' => $ticket];
            }
        }

        $severity = ['high' => 0, 'medium' => 1, 'low' => 2];
        $type = ['bottleneck' => 0, 'stale' => 1, 'blocked' => 2, 'orphan' => 3];
        usort($alerts, static fn (array $a, array $b) => [$severity[$a['severity']], $type[$a['type']], $a['ticket']->getNumber()]
            <=> [$severity[$b['severity']], $type[$b['type']], $b['ticket']->getNumber()]);

        return $alerts;
    }

    public function staleHours(): int
    {
        return $this->staleHours;
    }
}
