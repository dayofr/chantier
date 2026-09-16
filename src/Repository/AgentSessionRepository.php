<?php

namespace App\Repository;

use App\Entity\AgentSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgentSession> */
class AgentSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentSession::class);
    }

    public function findOneBySessionId(string $sessionId): ?AgentSession
    {
        return $this->findOneBy(['sessionId' => $sessionId]);
    }

    /**
     * @param list<string> $sessionIds
     *
     * @return array<string, AgentSession> indexées par sessionId
     */
    public function findBySessionIds(array $sessionIds): array
    {
        if ([] === $sessionIds) {
            return [];
        }

        $result = [];
        foreach ($this->findBy(['sessionId' => $sessionIds]) as $session) {
            $result[$session->getSessionId()] = $session;
        }

        return $result;
    }
}
