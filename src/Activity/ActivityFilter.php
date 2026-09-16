<?php

namespace App\Activity;

use App\Enum\ActivityType;
use Psr\Clock\ClockInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filtres du journal, lus depuis la query string.
 * Les valeurs inconnues sont ignorées plutôt que rejetées : l'URL reste tolérante.
 */
final class ActivityFilter
{
    public const array PERIODS = ['today', '7d', '30d'];
    public const int PAGE_SIZE = 50;
    public const int MAX_LIMIT = 500;

    /**
     * @param list<string> $type
     */
    public function __construct(
        public array $type = [],
        public ?string $ticket = null,
        public ?string $period = null,
        #[Assert\Length(max: 200)]
        public ?string $q = null,
        public ?string $session = null,
        #[Assert\Positive]
        public ?int $before = null,
        /** Inclure toutes les entrées jusqu'à cet id (rafraîchissement d'un journal déjà étendu). */
        #[Assert\Positive]
        public ?int $until = null,
        #[Assert\Range(min: 1, max: self::MAX_LIMIT)]
        public int $limit = self::PAGE_SIZE,
    ) {
    }

    /** @return list<ActivityType> */
    public function types(): array
    {
        return array_values(array_filter(array_map(static fn ($t) => \is_string($t) ? ActivityType::tryFrom($t) : null, $this->type)));
    }

    /** Clé de ticket, d'epic ou d'initiative, normalisée. */
    public function subject(): ?string
    {
        $key = strtoupper(trim((string) $this->ticket));

        return '' === $key ? null : $key;
    }

    /** @return list<string> termes de recherche normalisés */
    public function terms(): array
    {
        return SearchText::terms($this->q);
    }

    public function periodKey(): ?string
    {
        return \in_array($this->period, self::PERIODS, true) ? $this->period : null;
    }

    /** Début de la période, en UTC. "today" = minuit dans le fuseau d'affichage. */
    public function since(ClockInterface $clock, string $timezone): ?\DateTimeImmutable
    {
        $now = $clock->now();

        return match ($this->periodKey()) {
            'today' => $now->setTimezone(new \DateTimeZone($timezone))->setTime(0, 0)->setTimezone(new \DateTimeZone('UTC')),
            '7d' => $now->modify('-7 days'),
            '30d' => $now->modify('-30 days'),
            default => null,
        };
    }

    public function hasFilters(): bool
    {
        return [] !== $this->types() || null !== $this->subject() || null !== $this->periodKey() || [] !== $this->terms();
    }

    /**
     * Paramètres d'URL des filtres actifs (hors pagination), avec surcharges.
     * Une surcharge à null retire le paramètre.
     */
    public function toQuery(array $overrides = []): array
    {
        $query = array_merge([
            'type' => array_map(static fn (ActivityType $t) => $t->value, $this->types()) ?: null,
            'ticket' => $this->subject(),
            'period' => $this->periodKey(),
            'q' => [] !== $this->terms() ? trim((string) $this->q) : null,
            'session' => $this->session ?: null,
        ], $overrides);

        return array_filter($query, static fn ($v) => null !== $v && [] !== $v && '' !== $v);
    }
}
