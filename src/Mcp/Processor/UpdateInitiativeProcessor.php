<?php

namespace App\Mcp\Processor;

use App\Enum\PlanStatus;
use App\Enum\Risk;
use App\Mcp\Tool\UpdateInitiative;

/** @extends AbstractToolProcessor<UpdateInitiative> */
final class UpdateInitiativeProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $initiative = $this->lookup->initiative($data->initiative);

        if (null !== $data->title) {
            $initiative->setTitle($data->title);
        }
        if (null !== $data->description) {
            $initiative->setDescription('' === $data->description ? null : $data->description);
        }
        if (null !== $status = $this->enum(PlanStatus::class, $data->status, 'status')) {
            $initiative->setStatus($status);
        }
        if (null !== $risk = $this->enum(Risk::class, $data->risk, 'risk')) {
            $initiative->setRisk($risk);
        }
        if (null !== $data->position) {
            $initiative->setPosition($data->position);
        }
        $this->save($initiative);

        return $this->presenter->initiative($initiative);
    }
}
