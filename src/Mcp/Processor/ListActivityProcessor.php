<?php

namespace App\Mcp\Processor;

use App\Mcp\Tool\ListActivity;
use App\Repository\ActivityRepository;

/** @extends AbstractToolProcessor<ListActivity> */
final class ListActivityProcessor extends AbstractToolProcessor
{
    public function __construct(private readonly ActivityRepository $activities)
    {
    }

    protected function handle(object $data): array
    {
        $criteria = [];
        if (null !== $data->ticket && '' !== $data->ticket) {
            $criteria['ticket'] = $this->lookup->ticket($data->ticket);
        } elseif (null !== $data->project && '' !== $data->project) {
            $criteria['project'] = $this->lookup->project($data->project);
        }
        if (null !== $data->session && '' !== $data->session) {
            $criteria['sessionId'] = $data->session;
        }

        return ['activities' => array_map(
            $this->presenter->activity(...),
            $this->activities->findBy($criteria, ['createdAt' => 'DESC', 'id' => 'DESC'], $data->limit),
        )];
    }
}
