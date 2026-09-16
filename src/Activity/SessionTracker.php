<?php

namespace App\Activity;

use App\Entity\AgentSession;
use App\Repository\AgentSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Tient à jour la fiche de la session MCP en cours.
 * Écriture limitée : création, puis lastSeenAt au plus toutes les TOUCH_INTERVAL secondes,
 * pour qu'un agent qui lit beaucoup ne déclenche pas de rafraîchissement à chaque appel.
 */
final readonly class SessionTracker
{
    public const int TOUCH_INTERVAL = 300;

    public function __construct(
        private AgentSessionRepository $sessions,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    public function track(string $sessionId, string $client): AgentSession
    {
        $now = $this->clock->now();
        $session = $this->sessions->findOneBySessionId($sessionId);

        if (null === $session) {
            $session = new AgentSession($sessionId, $client, $now);
            $this->em->persist($session);
            $this->em->flush();
        } elseif ($now->getTimestamp() - $session->getLastSeenAt()->getTimestamp() >= self::TOUCH_INTERVAL) {
            $session->seenAt($now);
            $this->em->flush();
        }

        return $session;
    }
}
