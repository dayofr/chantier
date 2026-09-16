<?php

namespace App\Mcp\Processor;

use App\Enum\ProjectStatus;
use App\Mcp\Tool\UpdateProject;

/** @extends AbstractToolProcessor<UpdateProject> */
final class UpdateProjectProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $project = $this->lookup->project($data->project);

        if (null !== $data->name) {
            $project->setName($data->name);
        }
        if (null !== $data->description) {
            $project->setDescription('' === $data->description ? null : $data->description);
        }
        if (null !== $status = $this->enum(ProjectStatus::class, $data->status, 'status')) {
            $project->setStatus($status);
        }
        if (null !== $data->repository) {
            $project->setRepository('' === $data->repository ? null : $data->repository);
        }
        $this->save($project);

        return $this->presenter->project($project);
    }
}
