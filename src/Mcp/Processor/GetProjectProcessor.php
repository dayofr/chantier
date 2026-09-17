<?php

namespace App\Mcp\Processor;

use App\Mcp\Tool\GetProject;

/** @extends AbstractToolProcessor<GetProject> */
final class GetProjectProcessor extends AbstractToolProcessor
{
    protected const bool READ_ONLY = true;

    protected function handle(object $data): array
    {
        return $this->presenter->projectTree($this->lookup->project($data->project), $data->includeClosed);
    }
}
