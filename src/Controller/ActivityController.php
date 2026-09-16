<?php

namespace App\Controller;

use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class ActivityController extends AbstractController
{
    #[Route('/{_locale}/activity', name: 'activity', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
    public function index(
        ActivityRepository $activities,
        #[MapQueryParameter] ?string $session = null,
        #[MapQueryParameter] ?int $before = null,
    ): Response {
        return $this->render('activity/index.html.twig', [
            'project' => null,
            'entries' => $activities->findFeed(null, $session, $before),
            'sessions' => $activities->findSessions(),
            'session' => $session,
        ]);
    }
}
