<?php

namespace App\Mcp\Processor;

use App\Entity\Project;
use App\Mcp\Tool\CreateProject;

/** @extends AbstractToolProcessor<CreateProject> */
final class CreateProjectProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $project = new Project()
            ->setKey($data->key)
            ->setName($data->name)
            ->setDescription($data->description)
            ->setRepository($data->repository);
        $this->save($project);

        return $this->presenter->project($project);
    }
}
