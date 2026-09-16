<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum LinkType: string implements TranslatableInterface
{
    case PullRequest = 'pull_request';
    case Commit = 'commit';
    case Branch = 'branch';
    case File = 'file';
    case Url = 'url';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('link_type.'.$this->value, locale: $locale);
    }
}
