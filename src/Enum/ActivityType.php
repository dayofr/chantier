<?php

namespace App\Enum;

enum ActivityType: string
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
}
