<?php

namespace App\Activity;

use App\Entity\AgentSession;
use App\Repository\AgentSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Résout la séance de travail d'un appel d'outil MCP.
 *
 * - Protocole avec session (handshake) : la session MCP est la séance.
 * - Protocole sans état (2026-07-28, Claude Code) : pas d'identifiant de connexion.
 *   L'appel est rattaché à la dernière séance active du même client, ou en ouvre une.
 * - Identifiant explicite (paramètre "session") : prioritaire, pour les agents en parallèle.
 *
 * lastSeenAt est mis à jour au plus toutes les TOUCH_INTERVAL secondes, pour qu'un agent
 * actif ne déclenche pas le rafraîchissement en direct à chaque appel.
 */
final readonly class SessionTracker
{
    public const int TOUCH_INTERVAL = 300;

    public function __construct(
        private AgentSessionRepository $sessions,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private LockFactory $locks,
        /** Inactivité au-delà de laquelle un appel sans état ouvre une nouvelle séance. */
        #[Autowire(env: 'int:APP_SESSION_IDLE_HOURS')]
        private int $idleHours = 4,
    ) {
    }

    /** Séance d'une session MCP avec état : créée au premier appel. */
    public function track(string $sessionId, string $client): AgentSession
    {
        $session = $this->sessions->findOneBySessionId($sessionId);
        if (null === $session) {
            return $this->create($client, $sessionId);
        }

        return $this->touch($session);
    }

    /** Séance existante désignée explicitement, ou null si inconnue. */
    public function find(string $sessionId): ?AgentSession
    {
        $session = $this->sessions->findOneBySessionId($sessionId);

        return null === $session ? null : $this->touch($session);
    }

    /** Appel sans état : dernière séance active du client, sinon une nouvelle. */
    public function current(string $client): AgentSession
    {
        // Des appels parallèles au démarrage ne doivent pas ouvrir plusieurs séances.
        $lock = $this->locks->createLock('chantier-session-'.$client, 10);
        $lock->acquire(true);

        try {
            $since = $this->clock->now()->modify(\sprintf('-%d hours', $this->idleHours));
            $session = $this->sessions->findLatestActive($client, $since);

            return null === $session ? $this->create($client) : $this->touch($session);
        } finally {
            $lock->release();
        }
    }

    /** Ouvre une nouvelle séance (start_session sans état). */
    public function start(string $client): AgentSession
    {
        return $this->create($client);
    }

    private function create(string $client, ?string $sessionId = null): AgentSession
    {
        $session = new AgentSession($sessionId ?? Uuid::v7()->toRfc4122(), $client, $this->clock->now());
        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }

    private function touch(AgentSession $session): AgentSession
    {
        $now = $this->clock->now();
        if ($now->getTimestamp() - $session->getLastSeenAt()->getTimestamp() >= self::TOUCH_INTERVAL) {
            $session->seenAt($now);
            $this->em->flush();
        }

        return $session;
    }
}
