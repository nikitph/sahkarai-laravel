<?php

namespace App\Enums;

enum Tier: string
{
    case Free = 'free';
    case Tier1 = 'tier_1';
    case Tier2 = 'tier_2';

    public function canViewInterpretations(): bool
    {
        return $this !== self::Free;
    }

    public function canExportDocuments(): bool
    {
        return $this !== self::Free;
    }

    public function canReceiveNotifications(): bool
    {
        return $this !== self::Free;
    }

    public function canChat(): bool
    {
        return $this === self::Tier2;
    }
}
