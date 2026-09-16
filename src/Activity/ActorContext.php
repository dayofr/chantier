<?php

namespace App\Activity;

/**
 * Qui écrit, pendant la requête en cours. Rempli par les outils MCP.
 */
final class ActorContext
{
    private string $author = 'api';
    private ?string $sessionId = null;

    public function set(string $author, ?string $sessionId = null): void
    {
        $this->author = $author;
        $this->sessionId = $sessionId;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function stamp(\App\Entity\Activity $activity): \App\Entity\Activity
    {
        return $activity->setAuthor($this->author)->setSessionId($this->sessionId);
    }
}
