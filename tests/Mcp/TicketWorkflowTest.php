<?php

namespace App\Tests\Mcp;

final class TicketWorkflowTest extends McpTestCase
{
    public function testExposesAllTools(): void
    {
        $tools = $this->listTools();

        foreach (['list_projects', 'get_project', 'create_project', 'update_project', 'create_initiative', 'update_initiative',
            'create_epic', 'update_epic', 'create_tickets', 'update_ticket', 'get_ticket', 'search_tickets',
            'get_next_ticket', 'manage_subtasks', 'set_dependency', 'add_link', 'log_activity', 'list_activity'] as $name) {
            self::assertArrayHasKey($name, $tools);
        }
        self::assertSame(['low', 'medium', 'high', 'urgent'], $tools['update_ticket']['inputSchema']['properties']['priority']['enum']);
    }

    public function testFullWorkflow(): void
    {
        $this->seedProject();

        $created = $this->callTool('create_tickets', [
            'project' => 'CHANT',
            'epic' => 'CHANT-E1',
            'tickets' => [
                ['title' => 'Entités', 'priority' => 'high', 'subTasks' => ['Project', 'Ticket'], 'storyPoints' => 3],
                ['title' => 'Outils', 'blockedBy' => ['#0'], 'priority' => 'urgent'],
                ['title' => 'Orphelin', 'epic' => 'none', 'priority' => 'low'],
            ],
        ])['created'];
        self::assertSame(['CHANT-1', 'CHANT-2', 'CHANT-3'], array_column($created, 'key'));
        self::assertTrue($created[1]['blocked']);
        self::assertArrayNotHasKey('epic', $created[2]);

        // CHANT-2 est plus prioritaire mais bloqué : on doit recevoir CHANT-1.
        self::assertSame('CHANT-1', $this->callTool('get_next_ticket', ['project' => 'CHANT'])['ticket']['key']);

        $this->callTool('update_ticket', ['ticket' => 'CHANT-1', 'status' => 'in_progress']);
        $detail = $this->callTool('get_ticket', ['ticket' => 'chant-1']);
        self::assertNotNull($detail['startedAt']);

        $subTaskIds = array_column($detail['subTasks'], 'id');
        $detail = $this->callTool('manage_subtasks', ['ticket' => 'CHANT-1', 'complete' => $subTaskIds, 'add' => ['Epic']]);
        self::assertSame([true, true, false], array_column($detail['subTasks'], 'done'));

        $this->callTool('update_ticket', ['ticket' => 'CHANT-1', 'status' => 'done', 'comment' => 'Entités créées.']);

        // Débloqué : CHANT-2 (urgent) passe devant.
        self::assertSame('CHANT-2', $this->callTool('get_next_ticket', ['project' => 'CHANT'])['ticket']['key']);

        $tree = $this->callTool('get_project', ['project' => 'CHANT']);
        self::assertSame(33, $tree['stats']['progress']);
        self::assertSame(['CHANT-2'], array_column($tree['initiatives'][0]['epics'][0]['tickets'], 'key'));
        self::assertSame(['CHANT-3'], array_column($tree['orphanTickets'], 'key'));
        self::assertContains(['type' => 'orphan', 'severity' => 'low', 'ticket' => 'CHANT-3', 'title' => 'Orphelin'], $tree['alerts']);

        $activity = $this->callTool('list_activity', ['ticket' => 'CHANT-1'])['activities'];
        self::assertSame(['status_changed', 'note', 'status_changed', 'created'], array_column($activity, 'type'));
        self::assertSame(['kind' => 'ticket', 'from' => 'in_progress', 'to' => 'done'], $activity[0]['data']);
        self::assertSame('phpunit', $activity[0]['author']);
        self::assertNotEmpty($activity[0]['session']);
    }

    public function testSearchFilters(): void
    {
        $this->seedProject();
        $this->callTool('create_tickets', ['project' => 'CHANT', 'tickets' => [
            ['title' => 'Bug SQLite', 'type' => 'bug', 'labels' => ['db']],
            ['title' => 'Doc', 'type' => 'docs', 'status' => 'done'],
        ]]);

        self::assertSame(['CHANT-1'], array_column($this->callTool('search_tickets', ['label' => 'db'])['tickets'], 'key'));
        self::assertSame(['CHANT-1'], array_column($this->callTool('search_tickets', ['query' => 'sqlite'])['tickets'], 'key'));
        self::assertSame(['CHANT-2'], array_column($this->callTool('search_tickets', ['project' => 'CHANT', 'status' => ['done']])['tickets'], 'key'));
        self::assertSame(2, $this->callTool('search_tickets', ['orphan' => true])['count']);
    }

    public function testActivitySearch(): void
    {
        $this->seedProject();
        $this->callTool('create_tickets', ['project' => 'CHANT', 'epic' => 'CHANT-E1', 'tickets' => [['title' => 'Stockage']]]);
        $this->callTool('log_activity', ['ticket' => 'CHANT-1', 'type' => 'decision', 'message' => 'On garde SQLite, pas de Postgres.']);
        $this->callTool('log_activity', ['project' => 'CHANT', 'type' => 'note', 'message' => 'Réunion d\'équipe']);

        $found = $this->callTool('list_activity', ['query' => 'sqlite POSTGRES'])['activities'];
        self::assertSame(['On garde SQLite, pas de Postgres.'], array_column($found, 'message'));

        self::assertSame(['Réunion d\'équipe'], array_column($this->callTool('list_activity', ['query' => 'reunion equipe'])['activities'], 'message'));
        self::assertSame(['decision'], array_column($this->callTool('list_activity', ['ticket' => 'CHANT-E1', 'type' => ['decision']])['activities'], 'type'));
        self::assertSame([], $this->callTool('list_activity', ['query' => '%'])['activities'], 'Les jokers SQL sont échappés.');
    }

    public function testDependencyCycleIsRejected(): void
    {
        $this->seedProject();
        $this->callTool('create_tickets', ['project' => 'CHANT', 'tickets' => [['title' => 'A'], ['title' => 'B'], ['title' => 'C']]]);
        $this->callTool('set_dependency', ['source' => 'CHANT-1', 'target' => 'CHANT-2']);
        $this->callTool('set_dependency', ['source' => 'CHANT-2', 'target' => 'CHANT-3']);

        self::assertStringContainsString('Cycle', $this->callToolError('set_dependency', ['source' => 'CHANT-3', 'target' => 'CHANT-1']));

        $this->callTool('set_dependency', ['source' => 'CHANT-1', 'target' => 'CHANT-2', 'remove' => true]);
        self::assertFalse($this->callTool('get_ticket', ['ticket' => 'CHANT-2'])['blocked']);
    }

    public function testErrorsAreReadable(): void
    {
        $this->seedProject();

        self::assertStringContainsString('introuvable', $this->callToolError('get_ticket', ['ticket' => 'NOPE-1']));
        self::assertStringContainsString('Valeurs admises', $this->callToolError('create_tickets', ['project' => 'CHANT', 'tickets' => [['title' => 'x', 'priority' => 'huge']]]));
        self::assertStringContainsString('clé', $this->callToolError('create_project', ['key' => 'bad key', 'name' => 'X']));
        self::assertStringContainsString('réservé', $this->callToolError('log_activity', ['project' => 'CHANT', 'type' => 'created', 'message' => 'x']));

        // Un lot en erreur ne crée rien.
        $this->callToolError('create_tickets', ['project' => 'CHANT', 'tickets' => [['title' => 'ok'], ['title' => 'ko', 'epic' => 'CHANT-E99']]]);
        self::assertSame(0, $this->callTool('search_tickets', ['project' => 'CHANT'])['count']);
    }

    private function seedProject(): void
    {
        $this->callTool('create_project', ['key' => 'CHANT', 'name' => 'Chantier']);
        self::assertSame('CHANT-I1', $this->callTool('create_initiative', ['project' => 'CHANT', 'title' => 'API'])['key']);
        self::assertSame('CHANT-E1', $this->callTool('create_epic', ['initiative' => 'CHANT-I1', 'title' => 'MCP', 'targetDate' => '2026-09-30'])['key']);
    }
}
