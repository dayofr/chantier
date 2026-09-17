<?php

namespace App\Controller;

use App\Entity\Project;
use App\Service\KanbanBoard;
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
        KanbanBoard $board,
        #[MapQueryParameter] ?string $epic = null,
    ): Response {
        $epics = [];
        foreach ($project->getInitiatives() as $initiative) {
            array_push($epics, ...$initiative->getEpics());
        }
        $tickets = $board->filterByEpic($project->getTickets(), $epic);

        return $this->render('project/board.html.twig', [
            'project' => $project,
            'columns' => $board->columns($tickets),
            'epics' => $epics,
            'currentEpic' => $epic ? strtoupper($epic) : null,
            'filtered' => $tickets,
            'epicCounts' => $board->epicCounts($project->getTickets()),
        ]);
    }
}
