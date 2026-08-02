<?php

namespace App\Enums;

enum CreditReason: string
{
    case GrantCycle = 'grant_cycle';
    case DebitMessage = 'debit_message';
    case DebitExplainerVideo = 'debit_explainer_video';
    case RefundExplainerVideo = 'refund_explainer_video';
    case TopUp = 'topup';
    case Adjustment = 'adjustment';
}
