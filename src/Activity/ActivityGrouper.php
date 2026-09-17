<?php

namespace App\Activity;

use App\Entity\Activity;
use App\Enum\ActivityType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Regroupe les suites d'événements automatiques (créations, changements de statut)
 * pour faire ressortir ce que l'agent a écrit.
 *
 * Un groupe : au moins MIN_GROUP événements automatiques consécutifs, même séance, même jour.
 */
final readonly class ActivityGrouper
{
    public const int MIN_GROUP = 3;

    private const array AUTOMATIC = [ActivityType::Created, ActivityType::StatusChanged];

    public function __construct(
        #[Autowire(env: 'APP_TIMEZONE')]
        private string $timezone = 'UTC',
    ) {
    }

    /**
     * @param list<Activity> $entries dans l'ordre d'affichage
     *
     * @return list<array{kind: 'entry', entry: Activity}|array{kind: 'group', entries: list<Activity>, created: int, statusChanged: int}>
     */
    public function group(array $entries): array
    {
        $items = [];
        $run = [];

        foreach ($entries as $entry) {
            if ([] !== $run && !$this->continues($run[0], $entry)) {
                array_push($items, ...$this->flush($run));
                $run = [];
            }
            if ($this->isAutomatic($entry)) {
                $run[] = $entry;
            } else {
                $items[] = ['kind' => 'entry', 'entry' => $entry];
            }
        }

        return [...$items, ...$this->flush($run)];
    }

    private function continues(Activity $first, Activity $entry): bool
    {
        return $this->isAutomatic($entry)
            && $entry->getSessionId() === $first->getSessionId()
            && $this->day($entry) === $this->day($first);
    }

    /** @param list<Activity> $run */
    private function flush(array $run): array
    {
        if (\count($run) < self::MIN_GROUP) {
            return array_map(static fn (Activity $e) => ['kind' => 'entry', 'entry' => $e], $run);
        }

        $created = \count(array_filter($run, static fn (Activity $e) => ActivityType::Created === $e->getType()));

        return [['kind' => 'group', 'entries' => $run, 'created' => $created, 'statusChanged' => \count($run) - $created]];
    }

    private function isAutomatic(Activity $entry): bool
    {
        return \in_array($entry->getType(), self::AUTOMATIC, true) && null === $entry->getMessage();
    }

    private function day(Activity $entry): string
    {
        return $entry->getCreatedAt()->setTimezone(new \DateTimeZone($this->timezone))->format('Y-m-d');
    }
}
