<?php

namespace App\Controller;

use App\Activity\ActivityFilter;
use App\Activity\SessionReport;
use App\Repository\ActivityRepository;
use App\Repository\AgentSessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/** Fiche d'une séance de travail : résumé, décisions, bilan, journal. */
final class SessionController extends AbstractController
{
    #[Route('/{_locale}/sessions/{sessionId}', name: 'session_show', requirements: ['_locale' => '%app.locales%', 'sessionId' => '[A-Za-z0-9-]{1,100}'], methods: ['GET'])]
    public function show(
        string $sessionId,
        AgentSessionRepository $sessions,
        ActivityRepository $activities,
        SessionReport $report,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ActivityFilter $query = new ActivityFilter(),
    ): Response {
        $session = $sessions->findOneBySessionId($sessionId) ?? throw $this->createNotFoundException();

        // Journal de la session, avec la même pagination que la page Activité (AJAX et rafraîchissement).
        $filter = new ActivityFilter(session: $sessionId, before: $query->before, until: $query->until);
        ['entries' => $entries, 'hasMore' => $hasMore] = $activities->page($filter, null, null);

        return $this->render('session/show.html.twig', [
            'session' => $session,
            'report' => $report->for($session),
            'filter' => $filter,
            'entries' => $entries,
            'hasMore' => $hasMore,
            'feed_route' => 'activity',
            'feed_params' => [],
        ]);
    }
}
