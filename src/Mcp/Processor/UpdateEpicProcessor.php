<?php

namespace App\Mcp\Processor;

use App\Enum\PlanStatus;
use App\Mcp\ToolError;
use App\Mcp\Tool\UpdateEpic;

/** @extends AbstractToolProcessor<UpdateEpic> */
final class UpdateEpicProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $epic = $this->lookup->epic($data->epic);

        if (null !== $data->title) {
            $epic->setTitle($data->title);
        }
        if (null !== $data->description) {
            $epic->setDescription('' === $data->description ? null : $data->description);
        }
        if (null !== $status = $this->enum(PlanStatus::class, $data->status, 'status')) {
            $epic->setStatus($status);
        }
        if (null !== $data->startDate) {
            $epic->setStartDate($this->date($data->startDate, 'startDate'));
        }
        if (null !== $data->targetDate) {
            $epic->setTargetDate($this->date($data->targetDate, 'targetDate'));
        }
        if (null !== $data->initiative) {
            $initiative = $this->lookup->initiative($data->initiative);
            if ($initiative->getProject() !== $epic->getProject()) {
                throw new ToolError('L\'initiative cible doit appartenir au même projet.');
            }
            $epic->setInitiative($initiative);
        }
        if (null !== $data->position) {
            $epic->setPosition($data->position);
        }
        $this->save($epic);

        return $this->presenter->epic($epic);
    }
}
