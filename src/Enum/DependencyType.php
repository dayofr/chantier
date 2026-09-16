<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum DependencyType: string implements TranslatableInterface
{
    /** La source bloque la cible. */
    case Blocks = 'blocks';
    /** Simple lien entre deux tickets. */
    case RelatesTo = 'relates_to';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('dependency_type.'.$this->value, locale: $locale);
    }
}
