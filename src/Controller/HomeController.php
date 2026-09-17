<?php

namespace App\Controller;

use App\Enum\ProjectStatus;
use App\Enum\TicketStatus;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Service\TicketStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/{_locale}', name: 'portfolio', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
    public function portfolio(ProjectRepository $projects, ActivityRepository $activities): Response
    {
        $list = $projects->findAllWithTickets();
        $allTickets = [];
        $doneThisWeek = 0;
        $weekAgo = new \DateTimeImmutable('-7 days');

        foreach ($list as $project) {
            foreach ($project->getTickets() as $ticket) {
                $allTickets[] = $ticket;
                if (TicketStatus::Done === $ticket->getStatus() && $ticket->getCompletedAt() >= $weekAgo) {
                    ++$doneThisWeek;
                }
            }
        }

        return $this->render('portfolio/index.html.twig', [
            'projects' => $list,
            'stats' => TicketStats::of($allTickets),
            'activeProjects' => \count(array_filter($list, static fn ($p) => ProjectStatus::Active === $p->getStatus())),
            'doneThisWeek' => $doneThisWeek,
            'activityThisWeek' => $activities->countSince($weekAgo),
            'lastActivity' => $activities->lastActivityByProject(),
        ]);
    }
}
