<?php

namespace App\Activity;

use function Symfony\Component\String\u;

/** Normalisation commune au texte indexé et aux termes cherchés : minuscules, sans accents. */
final class SearchText
{
    public const int MAX_TERMS = 8;

    public static function normalize(?string ...$parts): string
    {
        $text = implode(' ', array_filter($parts, static fn ($p) => null !== $p && '' !== $p));

        return u($text)->ascii()->lower()->collapseWhitespace()->toString();
    }

    /** @return list<string> termes distincts, dans l'ordre */
    public static function terms(?string $query): array
    {
        $normalized = self::normalize($query);

        return '' === $normalized ? [] : \array_slice(array_values(array_unique(explode(' ', $normalized))), 0, self::MAX_TERMS);
    }
}
