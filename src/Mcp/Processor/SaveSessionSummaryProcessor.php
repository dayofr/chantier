<?php

namespace App\Mcp\Processor;

use App\Activity\ActorContext;
use App\Entity\Activity;
use App\Enum\ActivityType;
use App\Mcp\ToolError;
use App\Mcp\Tool\SaveSessionSummary;
use App\Repository\ActivityRepository;

/** @extends AbstractToolProcessor<SaveSessionSummary> */
final class SaveSessionSummaryProcessor extends AbstractToolProcessor
{
    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly ActorContext $actor,
    ) {
    }

    protected function handle(object $data): array
    {
        $session = $this->currentSession()->setSummary($data->summary);
        $defaultProject = null !== $data->project && '' !== $data->project ? $this->lookup->project($data->project) : null;

        $entities = [$session];
        $created = $skipped = 0;
        foreach (array_values($data->decisions) as $i => $decision) {
            $decision = (array) $decision;
            $text = trim((string) ($decision['text'] ?? ''));
            if ('' === $text) {
                throw new ToolError(\sprintf('decisions[%d].text : vide.', $i));
            }
            // Le résumé est renvoyé plusieurs fois : une décision déjà enregistrée n'est pas dupliquée.
            if (null !== $this->activities->findOneBy(['sessionId' => $session->getSessionId(), 'type' => ActivityType::Decision, 'message' => $text])) {
                ++$skipped;
                continue;
            }

            $ticketKey = $decision['ticket'] ?? null;
            if (null !== $ticketKey && '' !== $ticketKey) {
                $ticket = $this->lookup->ticket($ticketKey);
                $activity = new Activity($ticket->getProject(), ActivityType::Decision, $text)->setTicket($ticket);
            } elseif (null !== $defaultProject) {
                $activity = new Activity($defaultProject, ActivityType::Decision, $text);
            } else {
                throw new ToolError(\sprintf('decisions[%d] : préciser "ticket", ou "project" pour les décisions générales.', $i));
            }
            $entities[] = $this->actor->stamp($activity);
            ++$created;
        }

        $this->save(...$entities);

        return [
            'session' => $this->presenter->session($session),
            'decisions' => ['created' => $created, 'alreadyRecorded' => $skipped],
        ];
    }
}
