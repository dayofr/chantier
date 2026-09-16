<?php

namespace App\Enum;

enum TicketType: string
{
    case Feature = 'feature';
    case Bug = 'bug';
    case Chore = 'chore';
    case Refactor = 'refactor';
    case Spike = 'spike';
    case Docs = 'docs';
    case Test = 'test';
}
