<?php

namespace App\Mcp\Processor;

use App\Entity\AgentSession;
use App\Mcp\ToolError;
use App\Mcp\Tool\StartSession;
use App\Repository\AgentSessionRepository;

/** @extends AbstractToolProcessor<StartSession> */
final class StartSessionProcessor extends AbstractToolProcessor
{
    public function __construct(private readonly AgentSessionRepository $sessions)
    {
    }

    /**
     * Sans état, start_session ouvre toujours une nouvelle séance : les appels suivants du même
     * client s'y rattachent. Avec une session MCP ou un id explicite, la séance existante est renommée.
     */
    protected function resolveSession(): AgentSession
    {
        if (null !== $this->explicitSessionId) {
            return $this->sessionTracker->find($this->explicitSessionId)
                ?? throw new ToolError(\sprintf('Séance "%s" introuvable.', $this->explicitSessionId));
        }

        return null !== $this->mcpSessionId
            ? $this->sessionTracker->track($this->mcpSessionId, $this->client)
            : $this->sessionTracker->start($this->client);
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
            'reminder' => 'Les appels suivants sont rattachés à cette séance. Si plusieurs agents travaillent en parallèle, passer session: "'.$session->getSessionId().'" aux outils qui écrivent. Après chaque étape importante et en fin de séance : save_session_summary.',
        ];
    }
}
