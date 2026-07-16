<?php

namespace App\Enums;

enum Tier: string
{
    case Free = 'free';
    case Tier1 = 'tier_1';
    case Tier2 = 'tier_2';
    case Tier3 = 'tier_3';

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
        return in_array($this, [self::Tier2, self::Tier3], true);
    }

    public function canPersonalizeChat(): bool
    {
        return $this === self::Tier3;
    }

    public function rank(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Tier1 => 1,
            self::Tier2 => 2,
            self::Tier3 => 3,
        };
    }
}
