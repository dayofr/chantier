<?php

namespace App\Enum;

enum DependencyType: string
{
    /** La source bloque la cible. */
    case Blocks = 'blocks';
    /** Simple lien entre deux tickets. */
    case RelatesTo = 'relates_to';
}
