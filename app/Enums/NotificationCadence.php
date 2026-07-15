<?php

namespace App\Enums;

enum NotificationCadence: string
{
    case Immediate = 'immediate';
    case DailyDigest = 'daily_digest';
    case WeeklyDigest = 'weekly_digest';
}
