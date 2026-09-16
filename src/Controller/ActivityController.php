<?php

namespace App\Controller;

use App\Activity\ActivityFilter;
use App\Entity\Project;
use App\Enum\ActivityType;
use App\Repository\ActivityRepository;
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
        ClockInterface $clock,
        #[Autowire(env: 'APP_TIMEZONE')] string $timezone,
        #[MapEntity(mapping: ['key' => 'key'])] ?Project $project = null,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ActivityFilter $filter = new ActivityFilter(),
    ): Response {
        $since = $filter->since($clock, $timezone);
        // "until" remplace la taille de page, dans la limite de MAX_LIMIT.
        $limit = null !== $filter->until ? ActivityFilter::MAX_LIMIT : $filter->limit;
        // Une entrée de plus pour savoir s'il reste des entrées plus anciennes.
        $entries = $activities->feed($filter, $project, $since, $limit + 1);
        $hasMore = \count($entries) > $limit;
        $entries = \array_slice($entries, 0, $limit);
        if (null !== $filter->until && !$hasMore) {
            // Reste-t-il des entrées plus anciennes que la plus ancienne demandée ?
            $older = clone $filter;
            $older->before = $filter->until;
            $older->until = null;
            $hasMore = [] !== $activities->feed($older, $project, $since, 1);
        }

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

        return $this->render('activity/index.html.twig', [
            'project' => $project,
            'filter' => $filter,
            'entries' => $entries,
            'hasMore' => $hasMore,
            'typeCounts' => $activities->countByType($filter, $project, $since),
            'types' => ActivityType::cases(),
            'sessions' => $activities->findSessions($project),
            'session' => $filter->session,
        ]);
    }
}
