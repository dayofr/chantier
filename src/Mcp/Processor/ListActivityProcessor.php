<?php

namespace App\Mcp\Processor;

use App\Activity\ActivityFilter;
use App\Mcp\Tool\ListActivity;
use App\Repository\ActivityRepository;

/** @extends AbstractToolProcessor<ListActivity> */
final class ListActivityProcessor extends AbstractToolProcessor
{
    protected const bool READ_ONLY = true;

    public function __construct(private readonly ActivityRepository $activities)
    {
    }

    protected function handle(object $data): array
    {
        $project = null !== $data->project && '' !== $data->project ? $this->lookup->project($data->project) : null;
        $filter = new ActivityFilter(
            type: array_values($data->type),
            ticket: $data->ticket,
            q: $data->query,
            session: $data->session,
        );

        return ['activities' => array_map(
            $this->presenter->activity(...),
            $this->activities->feed($filter, $project, null, $data->limit),
        )];
    }
}
