<?php

namespace App\Repository;

use App\Entity\Epic;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\Priority;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Ticket> */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * @param list<TicketStatus> $statuses
     *
     * @return list<Ticket> triés par priorité décroissante puis numéro
     */
    public function search(
        ?Project $project = null,
        ?Epic $epic = null,
        array $statuses = [],
        ?Priority $priority = null,
        ?TicketType $type = null,
        ?string $label = null,
        ?string $query = null,
        ?bool $orphan = null,
        int $limit = 50,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->addSelect("CASE t.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END AS HIDDEN priorityRank")
            ->orderBy('priorityRank', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setMaxResults($limit);

        if (null !== $project) {
            $qb->andWhere('t.project = :project')->setParameter('project', $project);
        }
        if (null !== $epic) {
            $qb->andWhere('t.epic = :epic')->setParameter('epic', $epic);
        }
        if ([] !== $statuses) {
            $qb->andWhere('t.status IN (:statuses)')->setParameter('statuses', array_map(static fn (TicketStatus $s) => $s->value, $statuses));
        }
        if (null !== $priority) {
            $qb->andWhere('t.priority = :priority')->setParameter('priority', $priority->value);
        }
        if (null !== $type) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type->value);
        }
        if (null !== $label && '' !== $label) {
            // Labels stockés en JSON : on cherche la valeur entre guillemets.
            $qb->andWhere('t.labels LIKE :label')->setParameter('label', '%'.json_encode($label, \JSON_UNESCAPED_UNICODE).'%');
        }
        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('LOWER(t.key) LIKE :q OR LOWER(t.title) LIKE :q OR LOWER(t.description) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($query)).'%');
        }
        if (true === $orphan) {
            $qb->andWhere('t.epic IS NULL');
        } elseif (false === $orphan) {
            $qb->andWhere('t.epic IS NOT NULL');
        }

        return $qb->getQuery()->getResult();
    }
}
