<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ActivityType: string implements TranslatableInterface
{
    // Générés automatiquement.
    case Created = 'created';
    case StatusChanged = 'status_changed';
    // Écrits par l'agent.
    case Note = 'note';
    case Decision = 'decision';
    case Progress = 'progress';
    case Blocker = 'blocker';
    case Commit = 'commit';
    case Test = 'test';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('activity_type.'.$this->value, locale: $locale);
    }
}
