<?php

namespace App\Mcp\Processor;

use App\Entity\Ticket;
use App\Entity\TicketDependency;
use App\Enum\DependencyType;
use App\Mcp\ToolError;
use App\Mcp\Tool\SetDependency;

/** @extends AbstractToolProcessor<SetDependency> */
final class SetDependencyProcessor extends AbstractToolProcessor
{
    protected function handle(object $data): array
    {
        $source = $this->lookup->ticket($data->source);
        $target = $this->lookup->ticket($data->target);
        $type = $this->enum(DependencyType::class, $data->type, 'type') ?? DependencyType::Blocks;
        $existing = $this->em->getRepository(TicketDependency::class)->findOneBy(['source' => $source, 'target' => $target, 'type' => $type]);

        if ($data->remove) {
            if (null === $existing) {
                throw new ToolError('Aucun lien de ce type entre ces tickets.');
            }
            $source->getOutgoingDependencies()->removeElement($existing);
            $target->getIncomingDependencies()->removeElement($existing);
            $this->em->remove($existing);
            $this->em->flush();
        } elseif (null === $existing) {
            if (DependencyType::Blocks === $type && $this->reaches($target, $source)) {
                throw new ToolError(\sprintf('Cycle : %s bloque déjà %s, directement ou non.', $target->getKey(), $source->getKey()));
            }
            $this->save(new TicketDependency($source, $target, $type));
        }

        return ['source' => $this->presenter->ticket($source), 'target' => $this->presenter->ticket($target)];
    }

    /** Vrai si $from bloque $to via une chaîne de dépendances. */
    private function reaches(Ticket $from, Ticket $to): bool
    {
        $stack = [$from];
        $seen = [];
        while (null !== $current = array_pop($stack)) {
            if ($current === $to) {
                return true;
            }
            if (isset($seen[spl_object_id($current)])) {
                continue;
            }
            $seen[spl_object_id($current)] = true;
            foreach ($current->getOutgoingDependencies() as $dep) {
                if (DependencyType::Blocks === $dep->getType()) {
                    $stack[] = $dep->getTarget();
                }
            }
        }

        return false;
    }
}
