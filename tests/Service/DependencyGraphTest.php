<?php

namespace App\Tests\Service;

use App\Entity\Project;
use App\Entity\Ticket;
use App\Entity\TicketDependency;
use App\Enum\DependencyType;
use App\Enum\TicketStatus;
use App\Service\DependencyGraph;
use PHPUnit\Framework\TestCase;

final class DependencyGraphTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        $this->project = new Project('G', 'Graphe');
    }

    public function testNoDependenciesGivesNoGraph(): void
    {
        self::assertNull(new DependencyGraph()->around($this->ticket('Seul')));
    }

    public function testLayersFollowLongestBlockingPath(): void
    {
        [$a, $b, $c, $d, $related] = [$this->ticket('A'), $this->ticket('B'), $this->ticket('C'), $this->ticket('D'), $this->ticket('Lié')];
        new TicketDependency($a, $b);
        new TicketDependency($b, $c);
        new TicketDependency($a, $c); // Raccourci : C reste en couche 2.
        new TicketDependency($d, $c);
        new TicketDependency($related, $b, DependencyType::RelatesTo);
        $a->setStatus(TicketStatus::Done);

        $graph = new DependencyGraph()->around($b);

        $x = $this->columns($graph);
        self::assertSame($x['A'], $x['D']);
        self::assertGreaterThan($x['A'], $x['B']);
        self::assertGreaterThan($x['B'], $x['C']);
        self::assertArrayHasKey('Lié', $x);

        self::assertCount(5, $graph['edges']);
        $open = array_count_values(array_map(static fn ($e) => $e['type'].':'.($e['open'] ? 'open' : 'closed'), $graph['edges']));
        ksort($open);
        // A est terminé : ses deux flèches sont inactives. B et D bloquent encore C.
        self::assertSame(['blocks:closed' => 2, 'blocks:open' => 2, 'relates_to:closed' => 1], $open);

        $current = array_values(array_filter($graph['nodes'], static fn ($n) => $n['current']));
        self::assertSame('B', $current[0]['ticket']->getTitle());
    }

    public function testRelatedNeighboursAreNotExplored(): void
    {
        [$root, $related, $farAway] = [$this->ticket('Racine'), $this->ticket('Lié'), $this->ticket('Loin')];
        new TicketDependency($root, $related, DependencyType::RelatesTo);
        new TicketDependency($related, $farAway);

        self::assertSame(['Racine', 'Lié'], array_keys($this->columns(new DependencyGraph()->around($root))));
    }

    public function testCycleDoesNotLoopForever(): void
    {
        [$a, $b] = [$this->ticket('A'), $this->ticket('B')];
        new TicketDependency($a, $b);
        new TicketDependency($b, $a);

        self::assertCount(2, new DependencyGraph()->around($a)['nodes']);
    }

    public function testLargeComponentIsTruncated(): void
    {
        $root = $this->ticket('Racine');
        for ($i = 0; $i < DependencyGraph::MAX_NODES + 5; ++$i) {
            new TicketDependency($root, $this->ticket("T$i"));
        }

        $graph = new DependencyGraph()->around($root);

        self::assertTrue($graph['truncated']);
        self::assertCount(DependencyGraph::MAX_NODES, $graph['nodes']);
    }

    private function ticket(string $title): Ticket
    {
        $ticket = new Ticket($this->project, $title);
        $ticket->assignKey();

        return $ticket;
    }

    /** @return array<string, int> abscisse par titre */
    private function columns(array $graph): array
    {
        return array_column(array_map(static fn ($n) => [$n['ticket']->getTitle(), $n['x']], $graph['nodes']), 1, 0);
    }
}
