<?php

namespace App\Enum;

enum LinkType: string
{
    case PullRequest = 'pull_request';
    case Commit = 'commit';
    case Branch = 'branch';
    case File = 'file';
    case Url = 'url';
}
