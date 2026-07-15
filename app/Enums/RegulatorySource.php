<?php

namespace App\Enums;

enum RegulatorySource: string
{
    case Rbi = 'rbi';
    case IncomeTax = 'income_tax';
    case Gst = 'gst';
}
