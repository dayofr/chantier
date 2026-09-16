<?php

namespace App\Service;

use App\Entity\Ticket;
use App\Enum\DependencyType;

/**
 * Graphe des dépendances autour d'un ticket, mis en page en couches pour un rendu SVG.
 *
 * Couche = plus long chemin de blocage depuis une source. Ordre dans une couche : barycentre
 * des voisins de la couche précédente, quelques passes. Suffisant pour quelques dizaines de tickets.
 */
final class DependencyGraph
{
    public const int MAX_NODES = 40;
    public const int NODE_WIDTH = 220;
    public const int NODE_HEIGHT = 60;
    private const int GAP_X = 70;
    private const int GAP_Y = 18;
    private const int PADDING = 12;

    /**
     * @return array{
     *     width: int,
     *     height: int,
     *     truncated: bool,
     *     nodes: list<array{ticket: Ticket, x: int, y: int, current: bool}>,
     *     edges: list<array{path: string, type: string, open: bool}>
     * }|null null si le ticket n'a aucune dépendance
     */
    public function around(Ticket $root): ?array
    {
        [$tickets, $truncated] = $this->collect($root);
        if (\count($tickets) < 2) {
            return null;
        }

        $edges = $this->edges($tickets);
        $layers = $this->layers($tickets, $edges);
        $order = $this->order($tickets, $edges, $layers);

        $positions = [];
        $height = 0;
        foreach ($order as $layer => $ids) {
            foreach ($ids as $row => $id) {
                $positions[$id] = [
                    'x' => self::PADDING + $layer * (self::NODE_WIDTH + self::GAP_X),
                    'y' => self::PADDING + $row * (self::NODE_HEIGHT + self::GAP_Y),
                ];
            }
            $height = max($height, \count($ids));
        }

        $nodes = [];
        foreach ($positions as $id => $pos) {
            $nodes[] = ['ticket' => $tickets[$id], 'x' => $pos['x'], 'y' => $pos['y'], 'current' => $tickets[$id] === $root];
        }

        return [
            'width' => 2 * self::PADDING + \count($order) * self::NODE_WIDTH + (\count($order) - 1) * self::GAP_X,
            'height' => 2 * self::PADDING + $height * self::NODE_HEIGHT + ($height - 1) * self::GAP_Y,
            'truncated' => $truncated,
            'nodes' => $nodes,
            'edges' => array_map(fn (array $e) => [
                'path' => $this->path($positions[$e['from']], $positions[$e['to']]),
                'type' => $e['type']->value,
                'open' => DependencyType::Blocks === $e['type'] && !$tickets[$e['from']]->getStatus()->isClosed(),
            ], $edges),
        ];
    }

    /**
     * Composante connexe par liens "blocks", plus les liens "relates_to" directs du ticket.
     *
     * @return array{0: array<int, Ticket>, 1: bool}
     */
    private function collect(Ticket $root): array
    {
        $tickets = [spl_object_id($root) => $root];
        $queue = [$root];
        $truncated = false;

        while (null !== $ticket = array_shift($queue)) {
            foreach ($this->neighbours($ticket, $ticket === $root) as $next) {
                $id = spl_object_id($next);
                if (isset($tickets[$id])) {
                    continue;
                }
                if (\count($tickets) >= self::MAX_NODES) {
                    $truncated = true;
                    break 2;
                }
                $tickets[$id] = $next;
                // Les voisins "relates_to" ne sont pas explorés plus loin.
                if ($this->isBlockingNeighbour($ticket, $next)) {
                    $queue[] = $next;
                }
            }
        }

        return [$tickets, $truncated];
    }

    /** @return iterable<Ticket> */
    private function neighbours(Ticket $ticket, bool $withRelated): iterable
    {
        foreach ($ticket->getIncomingDependencies() as $dep) {
            if ($withRelated || DependencyType::Blocks === $dep->getType()) {
                yield $dep->getSource();
            }
        }
        foreach ($ticket->getOutgoingDependencies() as $dep) {
            if ($withRelated || DependencyType::Blocks === $dep->getType()) {
                yield $dep->getTarget();
            }
        }
    }

    private function isBlockingNeighbour(Ticket $a, Ticket $b): bool
    {
        foreach ([...$a->getIncomingDependencies(), ...$a->getOutgoingDependencies()] as $dep) {
            if (DependencyType::Blocks === $dep->getType() && ($dep->getSource() === $b || $dep->getTarget() === $b)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, Ticket> $tickets
     *
     * @return list<array{from: int, to: int, type: DependencyType}>
     */
    private function edges(array $tickets): array
    {
        $edges = [];
        foreach ($tickets as $id => $ticket) {
            foreach ($ticket->getOutgoingDependencies() as $dep) {
                $to = spl_object_id($dep->getTarget());
                if (isset($tickets[$to])) {
                    $edges[] = ['from' => $id, 'to' => $to, 'type' => $dep->getType()];
                }
            }
        }

        return $edges;
    }

    /**
     * @param array<int, Ticket>                                       $tickets
     * @param list<array{from: int, to: int, type: DependencyType}> $edges
     *
     * @return array<int, int> couche par ticket
     */
    private function layers(array $tickets, array $edges): array
    {
        $layer = array_fill_keys(array_keys($tickets), 0);

        // Relaxation du plus long chemin. Bornée : un cycle (possible via REST) ne boucle pas.
        for ($i = 0, $max = \count($tickets); $i < $max; ++$i) {
            $changed = false;
            foreach ($edges as $e) {
                if (DependencyType::Blocks === $e['type'] && $layer[$e['to']] < $layer[$e['from']] + 1 && $layer[$e['from']] + 1 < $max) {
                    $layer[$e['to']] = $layer[$e['from']] + 1;
                    $changed = true;
                }
            }
            if (!$changed) {
                break;
            }
        }

        return $layer;
    }

    /**
     * @param array<int, Ticket>                                       $tickets
     * @param list<array{from: int, to: int, type: DependencyType}> $edges
     * @param array<int, int>                                          $layers
     *
     * @return array<int, list<int>> ids ordonnés par couche, couches contiguës
     */
    private function order(array $tickets, array $edges, array $layers): array
    {
        // Compacte les couches vides.
        $used = array_values(array_unique($layers));
        sort($used);
        $rank = array_flip($used);

        $byLayer = [];
        foreach ($layers as $id => $layer) {
            $byLayer[$rank[$layer]][] = $id;
        }
        ksort($byLayer);
        foreach ($byLayer as &$ids) {
            usort($ids, static fn (int $a, int $b) => $tickets[$a]->getNumber() <=> $tickets[$b]->getNumber());
        }
        unset($ids);

        $neighbours = [];
        foreach ($edges as $e) {
            $neighbours[$e['to']][] = $e['from'];
            $neighbours[$e['from']][] = $e['to'];
        }

        for ($pass = 0; $pass < 4; ++$pass) {
            $position = [];
            foreach ($byLayer as $ids) {
                foreach ($ids as $row => $id) {
                    $position[$id] = $row;
                }
            }
            foreach ($byLayer as $layer => &$ids) {
                $score = [];
                foreach ($ids as $row => $id) {
                    $refs = array_filter($neighbours[$id] ?? [], static fn (int $n) => $rank[$layers[$n]] !== $layer);
                    $score[$id] = [] === $refs ? $row : array_sum(array_map(static fn (int $n) => $position[$n], $refs)) / \count($refs);
                }
                usort($ids, static fn (int $a, int $b) => [$score[$a], $tickets[$a]->getNumber()] <=> [$score[$b], $tickets[$b]->getNumber()]);
            }
            unset($ids);
        }

        return $byLayer;
    }

    /** Courbe de Bézier du bord droit de la source au bord gauche de la cible. */
    private function path(array $from, array $to): string
    {
        $half = self::NODE_HEIGHT / 2;

        if ($from['x'] === $to['x']) {
            // Même couche (lien "relates_to") : arc sur la droite.
            $x = $from['x'] + self::NODE_WIDTH;
            $bulge = $x + 40;

            return \sprintf('M %d %d C %d %d, %d %d, %d %d', $x, $from['y'] + $half, $bulge, $from['y'] + $half, $bulge, $to['y'] + $half, $x, $to['y'] + $half);
        }

        [$left, $right] = $from['x'] < $to['x'] ? [$from, $to] : [$to, $from];
        $x1 = $left['x'] + self::NODE_WIDTH;
        $x2 = $right['x'];
        $y1 = $left['y'] + $half;
        $y2 = $right['y'] + $half;

        if ($x2 - $x1 > self::GAP_X) {
            // Saute au moins une couche : passe dans l'espace entre deux lignes pour ne pas être masquée par les tickets.
            $lane = min($left['y'], $right['y']) - intdiv(self::GAP_Y, 2);
            $bend = intdiv(self::GAP_X, 2);
            $points = [[$x1, $y1], [$x1 + $bend, $y1], [$x1 + $bend, $lane], [$x1 + self::GAP_X, $lane], [$x2 - self::GAP_X, $lane], [$x2 - $bend, $lane], [$x2 - $bend, $y2], [$x2, $y2]];
            if ($from['x'] > $to['x']) {
                $points = array_reverse($points);
            }

            return vsprintf('M %d %d C %d %d, %d %d, %d %d L %d %d C %d %d, %d %d, %d %d', array_merge(...$points));
        }

        $mid = intdiv($x1 + $x2, 2);
        $start = [$x1, $y1];
        $end = [$x2, $y2];
        if ($from['x'] > $to['x']) {
            [$start, $end] = [$end, $start];
        }

        return \sprintf('M %d %d C %d %d, %d %d, %d %d', $start[0], $start[1], $mid, $start[1], $mid, $end[1], $end[0], $end[1]);
    }
}
