<?php

namespace App\Controller;

use App\Activity\ActivityFilter;
use App\Entity\Initiative;
use App\Enum\ActivityType;
use App\Repository\ActivityRepository;
use App\Service\KanbanBoard;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/** Vue d'une initiative : description, décisions et Kanban de ses tickets. */
final class InitiativeController extends AbstractController
{
    #[Route('/{_locale}/initiatives/{key}', name: 'initiative_show', requirements: ['_locale' => '%app.locales%', 'key' => '[A-Za-z][A-Za-z0-9]{1,9}-[Ii]\d+'], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['key' => 'key'])] Initiative $initiative,
        KanbanBoard $board,
        ActivityRepository $activities,
        #[MapQueryParameter] ?string $epic = null,
    ): Response {
        $epicKey = null;
        if (null !== $epic && '' !== $epic) {
            $epicKey = strtoupper($epic);
            if (!$initiative->getEpics()->exists(static fn ($i, $e) => $e->getKey() === $epicKey)) {
                throw $this->createNotFoundException();
            }
        }

        $allTickets = [];
        foreach ($initiative->getEpics() as $e) {
            array_push($allTickets, ...$e->getTickets());
        }
        $tickets = $board->filterByEpic($allTickets, $epicKey);

        // Décisions de l'initiative, de ses epics et de leurs tickets ; seulement celles de l'epic choisi s'il y en a un.
        $decisions = $activities->feed(
            new ActivityFilter(type: [ActivityType::Decision->value], ticket: $epicKey ?? $initiative->getKey()),
            $initiative->getProject(),
            null,
            ActivityFilter::MAX_LIMIT,
        );

        return $this->render('initiative/show.html.twig', [
            'project' => $initiative->getProject(),
            'initiative' => $initiative,
            'epics' => $initiative->getEpics()->getValues(),
            'epicCounts' => $board->epicCounts($allTickets),
            'currentEpic' => $epicKey,
            'filtered' => $tickets,
            'columns' => $board->columns($tickets),
            'decisions' => $decisions,
        ]);
    }
}
