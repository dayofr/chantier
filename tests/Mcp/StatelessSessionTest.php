<?php

namespace App\Tests\Mcp;

use App\Repository\AgentSessionRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Protocole MCP 2026-07-28 (sans état), tel que Claude Code l'utilise :
 * pas d'initialize, pas de Mcp-Session-Id, _meta et en-têtes MCP sur chaque requête.
 */
final class StatelessSessionTest extends WebTestCase
{
    use ResetDatabase;

    private const string VERSION = '2026-07-28';

    private KernelBrowser $client;
    private int $id = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testCallsFromSameClientShareOneSession(): void
    {
        $this->call('create_project', ['key' => 'SL', 'name' => 'Sans état']);
        $this->call('log_activity', ['project' => 'SL', 'message' => 'Une']);
        $this->call('log_activity', ['project' => 'SL', 'message' => 'Deux']);

        $sessions = $this->sessions()->findAll();
        self::assertCount(1, $sessions);
        self::assertSame('claude-code', $sessions[0]->getClient());

        $entries = $this->call('list_activity', ['project' => 'SL'])['activities'];
        self::assertSame([$sessions[0]->getSessionId()], array_values(array_unique(array_column($entries, 'session'))));
        self::assertSame(['claude-code'], array_values(array_unique(array_column($entries, 'author'))));
    }

    public function testReadOnlyToolsDoNotOpenSessions(): void
    {
        $this->call('list_projects');
        $this->call('search_tickets', ['query' => 'x']);

        self::assertCount(0, $this->sessions()->findAll());
    }

    public function testStartSessionOpensNewSessionThatFollowingCallsJoin(): void
    {
        $this->call('create_project', ['key' => 'SL', 'name' => 'Sans état']);
        $first = $this->sessions()->findAll()[0]->getSessionId();

        $started = $this->call('start_session', ['title' => 'Nouvelle séance']);
        $second = $started['session']['id'];
        self::assertNotSame($first, $second);
        self::assertStringContainsString($second, $started['reminder']);

        $this->call('log_activity', ['project' => 'SL', 'message' => 'Après start']);
        $saved = $this->call('save_session_summary', ['summary' => 'Résumé', 'decisions' => [['text' => 'Décidé.']], 'project' => 'SL']);
        self::assertSame($second, $saved['session']['id']);
        self::assertSame('Nouvelle séance', $saved['session']['title']);

        $last = $this->call('list_activity', ['project' => 'SL', 'limit' => 2])['activities'];
        self::assertSame([$second, $second], array_column($last, 'session'));
    }

    public function testExplicitSessionForParallelAgents(): void
    {
        $this->call('create_project', ['key' => 'SL', 'name' => 'Sans état']);
        $a = $this->call('start_session', ['title' => 'Agent A'])['session']['id'];
        $b = $this->call('start_session', ['title' => 'Agent B'])['session']['id'];

        // Sans id, le dernier démarrage gagne ; avec id, l'agent A garde sa séance.
        $this->call('log_activity', ['project' => 'SL', 'message' => 'Par A', 'session' => $a]);
        $this->call('log_activity', ['project' => 'SL', 'message' => 'Par défaut']);

        $entries = array_column($this->call('list_activity', ['project' => 'SL', 'type' => ['note']])['activities'], 'session', 'message');
        self::assertSame($a, $entries['Par A']);
        self::assertSame($b, $entries['Par défaut']);

        self::assertStringContainsString('introuvable', $this->callError('log_activity', ['project' => 'SL', 'message' => 'x', 'session' => 'nope']));
    }

    public function testDifferentClientsGetDifferentSessions(): void
    {
        $this->call('create_project', ['key' => 'SL', 'name' => 'Sans état']);
        $this->call('log_activity', ['project' => 'SL', 'message' => 'Autre outil'], clientName: 'cursor');

        self::assertEqualsCanonicalizing(['claude-code', 'cursor'], array_map(static fn ($s) => $s->getClient(), $this->sessions()->findAll()));
    }

    private function sessions(): AgentSessionRepository
    {
        return static::getContainer()->get(AgentSessionRepository::class);
    }

    private function call(string $tool, array $arguments = [], string $clientName = 'claude-code'): array
    {
        $result = $this->rpc($tool, $arguments, $clientName);
        self::assertFalse($result['isError'] ?? false, $result['content'][0]['text'] ?? '');

        return json_decode($result['content'][0]['text'], true, flags: \JSON_THROW_ON_ERROR);
    }

    private function callError(string $tool, array $arguments): string
    {
        $result = $this->rpc($tool, $arguments, 'claude-code');
        self::assertTrue($result['isError'] ?? false);

        return $result['content'][0]['text'];
    }

    private function rpc(string $tool, array $arguments, string $clientName): array
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => ++$this->id,
            'method' => 'tools/call',
            'params' => [
                'name' => $tool,
                'arguments' => (object) $arguments,
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => self::VERSION,
                    'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
                    'io.modelcontextprotocol/clientInfo' => ['name' => $clientName, 'version' => '2.5.0'],
                ],
            ],
        ];
        $this->client->request('POST', '/mcp', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => self::VERSION,
            'HTTP_MCP_METHOD' => 'tools/call',
            'HTTP_MCP_NAME' => $tool,
        ], content: json_encode($payload));

        $body = $this->client->getResponse()->getContent();
        // Réponse JSON ou flux SSE d'un seul message.
        if (preg_match('/^data: (.+)$/m', $body, $m)) {
            $body = $m[1];
        }
        $response = json_decode($body, true);
        self::assertIsArray($response, 'Réponse illisible : '.substr($body, 0, 300));
        self::assertArrayHasKey('result', $response, json_encode($response, \JSON_UNESCAPED_UNICODE));

        return $response['result'];
    }
}
