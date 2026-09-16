<?php

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Activity> */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * Journal paginé par curseur : entrées plus anciennes que $beforeId.
     *
     * @return list<Activity>
     */
    public function findFeed(?Project $project = null, ?string $sessionId = null, ?int $beforeId = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.ticket', 't')->addSelect('t')
            ->join('a.project', 'p')->addSelect('p')
            ->orderBy('a.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $project) {
            $qb->andWhere('a.project = :project')->setParameter('project', $project);
        }
        if (null !== $sessionId) {
            $qb->andWhere('a.sessionId = :session')->setParameter('session', $sessionId);
        }
        if (null !== $beforeId) {
            $qb->andWhere('a.id < :before')->setParameter('before', $beforeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Sessions récentes avec leur période et leur volume.
     *
     * @return list<array{sessionId: string, author: string, entries: int, firstAt: \DateTimeImmutable, lastAt: \DateTimeImmutable}>
     */
    public function findSessions(?Project $project = null, int $limit = 15): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.sessionId, MAX(a.author) AS author, COUNT(a.id) AS entries, MIN(a.createdAt) AS firstAt, MAX(a.createdAt) AS lastAt')
            ->andWhere('a.sessionId IS NOT NULL')
            ->groupBy('a.sessionId')
            ->orderBy('lastAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $project) {
            $qb->andWhere('a.project = :project')->setParameter('project', $project);
        }

        return array_map(static fn (array $row) => [
            'sessionId' => $row['sessionId'],
            'author' => $row['author'],
            'entries' => (int) $row['entries'],
            'firstAt' => new \DateTimeImmutable($row['firstAt']),
            'lastAt' => new \DateTimeImmutable($row['lastAt']),
        ], $qb->getQuery()->getArrayResult());
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.createdAt >= :since')->setParameter('since', $since)
            ->getQuery()->getSingleScalarResult();
    }

    /** @return array<string, \DateTimeImmutable> dernière activité par clé de projet */
    public function lastActivityByProject(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('p.key AS projectKey, MAX(a.createdAt) AS lastAt')
            ->join('a.project', 'p')
            ->groupBy('p.key')
            ->getQuery()->getArrayResult();

        return array_column(array_map(static fn ($r) => [$r['projectKey'], new \DateTimeImmutable($r['lastAt'])], $rows), 1, 0);
    }
}
