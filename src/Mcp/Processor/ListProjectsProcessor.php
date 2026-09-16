<?php

namespace App\Mcp\Processor;

use App\Entity\Project;
use App\Enum\ProjectStatus;
use App\Mcp\Tool\ListProjects;

/** @extends AbstractToolProcessor<ListProjects> */
final class ListProjectsProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $criteria = [];
        if (null !== $status = $this->enum(ProjectStatus::class, $data->status, 'status')) {
            $criteria['status'] = $status;
        }

        return ['projects' => array_map(
            $this->presenter->project(...),
            $this->em->getRepository(Project::class)->findBy($criteria, ['name' => 'ASC']),
        )];
    }
}
