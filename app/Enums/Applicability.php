<?php

namespace App\Enums;

enum Applicability: string
{
    case Pacs = 'pacs';
    case Ucb = 'ucb';
    case Dccb = 'dccb';
    case Stcb = 'stcb';
    case Apex = 'apex';
    case Generic = 'generic';
}
