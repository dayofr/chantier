<?php

namespace App\Repository;

use App\Entity\Project;
use App\Enum\ProjectStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Project> */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /** @return list<Project> actifs d'abord, archivés en dernier, puis par nom */
    public function findForSidebar(): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('CASE WHEN p.status = :archived THEN 1 ELSE 0 END AS HIDDEN archivedRank')
            ->setParameter('archived', ProjectStatus::Archived->value)
            ->orderBy('archivedRank', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Project> avec tickets et initiatives chargés */
    public function findAllWithTickets(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.tickets', 't')->addSelect('t')
            ->addSelect('CASE WHEN p.status = :archived THEN 1 ELSE 0 END AS HIDDEN archivedRank')
            ->setParameter('archived', ProjectStatus::Archived->value)
            ->orderBy('archivedRank', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
