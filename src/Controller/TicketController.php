<?php

namespace App\Controller;

use App\Enum\DependencyType;
use App\Repository\ActivityRepository;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TicketController extends AbstractController
{
    #[Route('/{_locale}/tickets/{key}', name: 'ticket_show', requirements: ['_locale' => '%app.locales%', 'key' => '[A-Za-z][A-Za-z0-9]{1,9}-\d+'], methods: ['GET'])]
    public function show(string $key, TicketRepository $tickets, ActivityRepository $activities): Response
    {
        $ticket = $tickets->findOneBy(['key' => strtoupper($key)])
            ?? throw $this->createNotFoundException();

        $groups = ['blocked_by' => [], 'blocks' => [], 'related' => []];
        foreach ($ticket->getIncomingDependencies() as $dep) {
            $groups[DependencyType::Blocks === $dep->getType() ? 'blocked_by' : 'related'][] = $dep->getSource();
        }
        foreach ($ticket->getOutgoingDependencies() as $dep) {
            $groups[DependencyType::Blocks === $dep->getType() ? 'blocks' : 'related'][] = $dep->getTarget();
        }

        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
            'dependencies' => array_filter($groups),
            'project' => $ticket->getProject(),
            'activity' => $activities->findBy(['ticket' => $ticket], ['id' => 'DESC'], 100),
        ]);
    }
}
