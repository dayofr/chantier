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
