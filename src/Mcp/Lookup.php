<?php

namespace App\Mcp;

use App\Entity\Epic;
use App\Entity\Initiative;
use App\Entity\Project;
use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;

/** Retrouve les éléments par clé, avec un message clair si absent. */
final readonly class Lookup
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function project(?string $key): Project
    {
        return $this->find(Project::class, 'Projet', $key);
    }

    public function initiative(?string $key): Initiative
    {
        return $this->find(Initiative::class, 'Initiative', $key);
    }

    public function epic(?string $key): Epic
    {
        return $this->find(Epic::class, 'Epic', $key);
    }

    public function ticket(?string $key): Ticket
    {
        return $this->find(Ticket::class, 'Ticket', $key);
    }

    /**
     * Ticket, epic ou initiative d'après la forme de la clé : CHANT-12, CHANT-E3, CHANT-I1.
     */
    public function subject(?string $key): Ticket|Epic|Initiative
    {
        $key = strtoupper(trim((string) $key));

        return match (1) {
            preg_match('/^[A-Z][A-Z0-9]*-I\d+$/', $key) => $this->initiative($key),
            preg_match('/^[A-Z][A-Z0-9]*-E\d+$/', $key) => $this->epic($key),
            preg_match('/^[A-Z][A-Z0-9]*-\d+$/', $key) => $this->ticket($key),
            default => throw new ToolError(\sprintf('Sujet "%s" invalide : attendu une clé de ticket (CHANT-12), d\'epic (CHANT-E3) ou d\'initiative (CHANT-I1).', $key)),
        };
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function find(string $class, string $label, ?string $key): object
    {
        $key = strtoupper(trim((string) $key));
        if ('' === $key) {
            throw new ToolError(\sprintf('%s : clé manquante.', $label));
        }

        return $this->em->getRepository($class)->findOneBy(['key' => $key])
            ?? throw new ToolError(\sprintf('%s "%s" introuvable.', $label, $key));
    }
}
