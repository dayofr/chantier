<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Risk: string implements TranslatableInterface
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('risk.'.$this->value, locale: $locale);
    }
}
