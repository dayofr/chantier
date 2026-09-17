<?php

namespace App\Controller;

use App\Entity\Epic;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\TicketStatus;
use App\Service\ProjectAlerts;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/projects/{key}', requirements: ['_locale' => '%app.locales%', 'key' => '[A-Za-z][A-Za-z0-9]{1,9}'], methods: ['GET'])]
final class ProjectController extends AbstractController
{
    /** Colonnes du Kanban, dans l'ordre. Les tickets annulés sont masqués. */
    /** Cartes visibles dans la colonne Terminé avant le repli des plus anciennes. */
    public const int DONE_VISIBLE = 10;

    private const array BOARD_COLUMNS = [
        TicketStatus::Backlog,
        TicketStatus::Todo,
        TicketStatus::InProgress,
        TicketStatus::InReview,
        TicketStatus::Blocked,
        TicketStatus::Done,
    ];

    #[Route('', name: 'project_show')]
    public function show(#[MapEntity(mapping: ['key' => 'key'])] Project $project, ProjectAlerts $alerts): Response
    {
        return $this->render('project/show.html.twig', [
            'project' => $project,
            'alerts' => $alerts->for($project),
            'staleHours' => $alerts->staleHours(),
        ]);
    }

    #[Route('/board', name: 'project_board')]
    public function board(
        #[MapEntity(mapping: ['key' => 'key'])] Project $project,
        #[MapQueryParameter] ?string $epic = null,
    ): Response {
        $epics = [];
        foreach ($project->getInitiatives() as $initiative) {
            array_push($epics, ...$initiative->getEpics());
        }

        $tickets = $project->getTickets()->filter(static fn (Ticket $t) => match ($epic) {
            null, '' => true,
            'none' => null === $t->getEpic(),
            default => $t->getEpic()?->getKey() === strtoupper($epic),
        });

        $columns = array_fill_keys(array_map(static fn (TicketStatus $s) => $s->value, self::BOARD_COLUMNS), []);
        foreach ($tickets as $ticket) {
            if (isset($columns[$ticket->getStatus()->value])) {
                $columns[$ticket->getStatus()->value][] = $ticket;
            }
        }
        foreach ($columns as $status => &$column) {
            usort($column, TicketStatus::Done->value === $status
                // Terminés : les plus récemment finis d'abord.
                ? static fn (Ticket $a, Ticket $b) => [$b->getCompletedAt(), $b->getNumber()] <=> [$a->getCompletedAt(), $a->getNumber()]
                : static fn (Ticket $a, Ticket $b) => [$b->getPriority()->weight(), $a->getNumber()] <=> [$a->getPriority()->weight(), $b->getNumber()]);
        }
        unset($column);

        return $this->render('project/board.html.twig', [
            'project' => $project,
            'columns' => $columns,
            'statuses' => self::BOARD_COLUMNS,
            'epics' => $epics,
            'currentEpic' => $epic ? strtoupper($epic) : null,
            'filtered' => $tickets,
            'epicCounts' => array_count_values(array_map(static fn (Ticket $t) => $t->getEpic()?->getKey() ?? 'NONE', $project->getTickets()->filter(static fn (Ticket $t) => TicketStatus::Cancelled !== $t->getStatus())->getValues())),
        ]);
    }
}
