<?php

namespace App\Mcp\Processor;

use App\Entity\SubTask;
use App\Mcp\ToolError;
use App\Mcp\Tool\ManageSubTasks;

/** @extends AbstractToolProcessor<ManageSubTasks> */
final class ManageSubTasksProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $ticket = $this->lookup->ticket($data->ticket);
        $byId = [];
        foreach ($ticket->getSubTasks() as $subTask) {
            $byId[$subTask->getId()] = $subTask;
        }
        $find = static fn (mixed $id): SubTask => $byId[(int) $id]
            ?? throw new ToolError(\sprintf('Sous-tâche %s introuvable sur %s. Ids valides : %s.', $id, $ticket->getKey(), implode(', ', array_keys($byId)) ?: 'aucun'));

        foreach ($data->complete as $id) {
            $find($id)->setDone(true);
        }
        foreach ($data->reopen as $id) {
            $find($id)->setDone(false);
        }
        foreach ($data->remove as $id) {
            $ticket->getSubTasks()->removeElement($find($id));
        }
        foreach ($data->add as $title) {
            $ticket->addSubTask((string) $title);
        }
        $this->save($ticket, ...$ticket->getSubTasks());

        return $this->presenter->ticketDetail($ticket);
    }
}
