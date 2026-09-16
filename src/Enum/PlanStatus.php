<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Statut d'une initiative ou d'un epic. */
enum PlanStatus: string implements TranslatableInterface
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('plan_status.'.$this->value, locale: $locale);
    }
}
