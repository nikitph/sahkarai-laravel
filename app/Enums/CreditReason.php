<?php

namespace App\Enums;

enum CreditReason: string
{
    case GrantCycle = 'grant_cycle';
    case DebitMessage = 'debit_message';
    case TopUp = 'topup';
    case Adjustment = 'adjustment';
}
