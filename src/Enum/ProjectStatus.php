<?php

namespace App\Enum;

enum ProjectStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Paused = 'paused';
    case Done = 'done';
    case Archived = 'archived';
}
