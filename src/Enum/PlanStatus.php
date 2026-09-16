<?php

namespace App\Enum;

/** Statut d'une initiative ou d'un epic. */
enum PlanStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
