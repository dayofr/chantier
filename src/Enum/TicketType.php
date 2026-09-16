<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum TicketType: string implements TranslatableInterface
{
    case Feature = 'feature';
    case Bug = 'bug';
    case Chore = 'chore';
    case Refactor = 'refactor';
    case Spike = 'spike';
    case Docs = 'docs';
    case Test = 'test';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('ticket_type.'.$this->value, locale: $locale);
    }
}
