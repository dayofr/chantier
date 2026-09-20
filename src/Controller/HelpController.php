<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Page d'aide : brancher un agent (Claude Code, Codex, OpenCode, autres) sur Chantier. */
final class HelpController extends AbstractController
{
    #[Route('/{_locale}/help', name: 'help', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
    public function index(Request $request): Response
    {
        $baseUrl = $request->getSchemeAndHttpHost();

        return $this->render('help/index.html.twig', [
            'baseUrl' => $baseUrl,
            'mcpUrl' => $baseUrl.'/mcp',
        ]);
    }
}
