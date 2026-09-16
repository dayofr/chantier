<?php

namespace App\Mcp\Processor;

use App\Entity\Initiative;
use App\Enum\PlanStatus;
use App\Enum\Risk;
use App\Mcp\Tool\CreateInitiative;

/** @extends AbstractToolProcessor<CreateInitiative> */
final class CreateInitiativeProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $project = $this->lookup->project($data->project);
        $initiative = new Initiative($project, $data->title)
            ->setDescription($data->description)
            ->setStatus($this->enum(PlanStatus::class, $data->status, 'status') ?? PlanStatus::Planned)
            ->setRisk($this->enum(Risk::class, $data->risk, 'risk') ?? Risk::Low)
            ->setPosition(\count($project->getInitiatives()));
        $this->save($initiative);

        return $this->presenter->initiative($initiative);
    }
}
