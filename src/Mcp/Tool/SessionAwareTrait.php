<?php

namespace App\Mcp\Tool;

/** Paramètre commun aux outils qui écrivent : séance explicite. */
trait SessionAwareTrait
{
    /** Id de séance renvoyé par start_session. Utile seulement si plusieurs agents travaillent en parallèle ; sinon la séance est déduite. */
    public ?string $session = null;
}
