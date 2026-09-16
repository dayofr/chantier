<?php

namespace App\Controller;

use App\Live\DataVersion;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LiveController
{
    #[Route('/_live/version', name: 'live_version', methods: ['GET'])]
    public function version(DataVersion $version): JsonResponse
    {
        $response = new JsonResponse(['version' => $version->get()]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
