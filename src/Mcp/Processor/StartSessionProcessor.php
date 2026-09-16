<?php

namespace App\Mcp\Processor;

use App\Entity\AgentSession;
use App\Mcp\Tool\StartSession;
use App\Repository\AgentSessionRepository;

/** @extends AbstractToolProcessor<StartSession> */
final class StartSessionProcessor extends AbstractToolProcessor
{
    public function __construct(private readonly AgentSessionRepository $sessions)
    {
    }

    protected function handle(object $data): array
    {
        $session = $this->currentSession()->setTitle($data->title);
        if (null !== $data->branch) {
            $session->setBranch($data->branch);
        }
        $this->save($session);

        $previous = array_filter(
            $this->sessions->findBy([], ['lastSeenAt' => 'DESC'], 10),
            static fn (AgentSession $s) => $s !== $session && null !== $s->getSummary(),
        );

        return [
            'session' => $this->presenter->session($session),
            'previousSessions' => array_map($this->presenter->session(...), \array_slice(array_values($previous), 0, 3)),
            'reminder' => 'En fin de séance ou après une étape importante : save_session_summary avec le résumé de la conversation et les décisions prises.',
        ];
    }
}
