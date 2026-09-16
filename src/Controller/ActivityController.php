<?php

namespace App\Controller;

use App\Activity\ActivityFilter;
use App\Entity\Project;
use App\Enum\ActivityType;
use App\Repository\ActivityRepository;
use App\Repository\AgentSessionRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/** Journal d'activité, global ou limité à un projet. */
final class ActivityController extends AbstractController
{
    #[Route('/{_locale}/activity', name: 'activity', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
    #[Route('/{_locale}/projects/{key}/activity', name: 'project_activity', requirements: ['_locale' => '%app.locales%', 'key' => '[A-Za-z][A-Za-z0-9]{1,9}'], methods: ['GET'])]
    public function index(
        Request $request,
        ActivityRepository $activities,
        AgentSessionRepository $agentSessions,
        ClockInterface $clock,
        #[Autowire(env: 'APP_TIMEZONE')] string $timezone,
        #[MapEntity(mapping: ['key' => 'key'])] ?Project $project = null,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ActivityFilter $filter = new ActivityFilter(),
    ): Response {
        $since = $filter->since($clock, $timezone);
        ['entries' => $entries, 'hasMore' => $hasMore] = $activities->page($filter, $project, $since);

        // Chargement AJAX de « Plus ancien » : seulement les entrées et le lien suivant.
        if ($request->query->getBoolean('fragment')) {
            return $this->render('activity/_fragment.html.twig', [
                'project' => $project,
                'filter' => $filter,
                'entries' => $entries,
                'hasMore' => $hasMore,
                'feed_route' => null === $project ? 'activity' : 'project_activity',
                'feed_params' => null === $project ? [] : ['key' => $project->getKey()],
            ]);
        }

        $sessions = $activities->findSessions($project);
        $sessionCards = $agentSessions->findBySessionIds(array_column($sessions, 'sessionId'));
        if (null !== $filter->session && !isset($sessionCards[$filter->session])) {
            $sessionCards += $agentSessions->findBySessionIds([$filter->session]);
        }

        return $this->render('activity/index.html.twig', [
            'project' => $project,
            'filter' => $filter,
            'entries' => $entries,
            'hasMore' => $hasMore,
            'typeCounts' => $activities->countByType($filter, $project, $since),
            'types' => ActivityType::cases(),
            'sessions' => $sessions,
            'sessionCards' => $sessionCards,
            'session' => $filter->session,
        ]);
    }
}
