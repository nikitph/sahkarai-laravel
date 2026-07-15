<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Free = 'free';
    case Pending = 'pending';
    case Active = 'active';
    case Halted = 'halted';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
