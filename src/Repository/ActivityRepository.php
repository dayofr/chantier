<?php

namespace App\Repository;

use App\Entity\Activity;
use App\Activity\ActivityFilter;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Activity> */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * Journal filtré, du plus récent au plus ancien, paginé par curseur (id).
     *
     * @return list<Activity>
     */
    public function feed(ActivityFilter $filter, ?Project $project, ?\DateTimeImmutable $since, int $limit): array
    {
        $qb = $this->filtered($filter, $project, $since)
            ->addSelect('t', 'p')
            ->join('a.project', 'p')
            ->orderBy('a.id', 'DESC')
            ->setMaxResults($limit);

        if ([] !== $types = $filter->types()) {
            $qb->andWhere('a.type IN (:types)')->setParameter('types', array_map(static fn ($t) => $t->value, $types));
        }
        if (null !== $filter->before) {
            $qb->andWhere('a.id < :before')->setParameter('before', $filter->before);
        }
        if (null !== $filter->until) {
            $qb->andWhere('a.id >= :until')->setParameter('until', $filter->until);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Une page du journal. Avec "until", toutes les entrées jusqu'à cet id (dans la limite de MAX_LIMIT).
     *
     * @return array{entries: list<Activity>, hasMore: bool}
     */
    public function page(ActivityFilter $filter, ?Project $project, ?\DateTimeImmutable $since): array
    {
        $limit = null !== $filter->until ? ActivityFilter::MAX_LIMIT : $filter->limit;
        // Une entrée de plus pour savoir s'il reste des entrées plus anciennes.
        $entries = $this->feed($filter, $project, $since, $limit + 1);
        $hasMore = \count($entries) > $limit;
        $entries = \array_slice($entries, 0, $limit);

        if (null !== $filter->until && !$hasMore) {
            $older = clone $filter;
            $older->before = $filter->until;
            $older->until = null;
            $hasMore = [] !== $this->feed($older, $project, $since, 1);
        }

        return ['entries' => $entries, 'hasMore' => $hasMore];
    }

    /**
     * Nombre d'entrées par type, avec les autres filtres appliqués.
     *
     * @return array<string, int>
     */
    public function countByType(ActivityFilter $filter, ?Project $project, ?\DateTimeImmutable $since): array
    {
        $rows = $this->filtered($filter, $project, $since)
            ->select('a.type AS type, COUNT(a.id) AS total')
            ->groupBy('a.type')
            ->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $type = $row['type'] instanceof \BackedEnum ? $row['type']->value : $row['type'];
            $counts[$type] = (int) $row['total'];
        }

        return $counts;
    }

    /** Filtres communs : projet, session, sujet, période. Le type et le curseur sont appliqués à part. */
    private function filtered(ActivityFilter $filter, ?Project $project, ?\DateTimeImmutable $since): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')->leftJoin('a.ticket', 't');

        if (null !== $project) {
            $qb->andWhere('a.project = :project')->setParameter('project', $project);
        }
        if (null !== $filter->session && '' !== $filter->session) {
            $qb->andWhere('a.sessionId = :session')->setParameter('session', $filter->session);
        }
        if (null !== $subject = $filter->subject()) {
            // Ticket, epic ou initiative : entrées du sujet lui-même et des tickets qu'il contient.
            $qb->leftJoin('t.epic', 'se')->leftJoin('se.initiative', 'si')
                ->andWhere('a.subjectKey = :subject OR se.key = :subject OR si.key = :subject')
                ->setParameter('subject', $subject);
        }
        if (null !== $since) {
            $qb->andWhere('a.createdAt >= :since')->setParameter('since', $since);
        }
        // Chaque terme doit apparaître, dans le texte indexé ou le titre actuel du ticket.
        foreach ($filter->terms() as $i => $term) {
            $param = ':term'.$i;
            $qb->andWhere("a.searchText LIKE $param ESCAPE '!' OR LOWER(t.title) LIKE $param ESCAPE '!'")
                ->setParameter('term'.$i, '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%');
        }

        return $qb;
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

    /** @return array<int, \DateTimeImmutable> dernière activité par id de ticket */
    public function lastActivityByTicket(Project $project): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.ticket) AS ticketId, MAX(a.createdAt) AS lastAt')
            ->andWhere('a.project = :project')->setParameter('project', $project)
            ->andWhere('a.ticket IS NOT NULL')
            ->groupBy('a.ticket')
            ->getQuery()->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['ticketId']] = new \DateTimeImmutable($row['lastAt']);
        }

        return $result;
    }

    public function latestId(): int
    {
        return (int) $this->createQueryBuilder('a')->select('MAX(a.id)')->getQuery()->getSingleScalarResult();
    }
}
