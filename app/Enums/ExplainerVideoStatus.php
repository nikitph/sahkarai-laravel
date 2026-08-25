<?php

namespace App\Enums;

enum ExplainerVideoStatus: string
{
    case NotRequested = 'not_requested';
    case Queued = 'queued';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';
}
