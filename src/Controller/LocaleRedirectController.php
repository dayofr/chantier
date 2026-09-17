<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Adresses de l'interface sans langue : / et /activity, /projects/CHANT…
 * Redirige vers la langue préférée du navigateur, si la page existe dans cette langue.
 */
final class LocaleRedirectController
{
    private const array LOCALES = ['fr', 'en'];

    public function __construct(private readonly RouterInterface $router)
    {
    }

    #[Route('/', name: 'root', methods: ['GET'])]
    #[Route('/{path}', name: 'locale_redirect', requirements: ['path' => '(?!(?:fr|en|api|mcp|_[a-z]+|assets|bundles|claude-code)(?:/|$)).+'], methods: ['GET'], priority: -100)]
    public function __invoke(Request $request, string $path = ''): RedirectResponse
    {
        $locale = $request->getPreferredLanguage(self::LOCALES) ?? self::LOCALES[0];
        $target = '/'.$locale.('' === $path ? '' : '/'.$path);

        if ('' !== $path) {
            try {
                $this->router->match($target);
            } catch (RoutingException) {
                throw new NotFoundHttpException();
            }
        }

        $query = $request->getQueryString();

        return new RedirectResponse($request->getBaseUrl().$target.(null !== $query ? '?'.$query : ''));
    }
}
