<?php

namespace App\Mcp\Processor;

use App\Entity\Epic;
use App\Enum\PlanStatus;
use App\Mcp\Tool\CreateEpic;

/** @extends AbstractToolProcessor<CreateEpic> */
final class CreateEpicProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $initiative = $this->lookup->initiative($data->initiative);
        $epic = new Epic($initiative, $data->title)
            ->setDescription($data->description)
            ->setStatus($this->enum(PlanStatus::class, $data->status, 'status') ?? PlanStatus::Planned)
            ->setStartDate($this->date($data->startDate, 'startDate'))
            ->setTargetDate($this->date($data->targetDate, 'targetDate'))
            ->setPosition(\count($initiative->getEpics()));
        $this->save($epic);

        return $this->presenter->epic($epic);
    }
}
