<?php

namespace App\Enums;

enum RegulatorySource: string
{
    case Rbi = 'rbi';
    case IncomeTax = 'income_tax';
    case Gst = 'gst';
    case Cbic = 'cbic';
    case Nabard = 'nabard';
    case UserUpload = 'user_upload';

    /** @return array<int, self> */
    public static function pollableCases(): array
    {
        return [self::Rbi, self::IncomeTax, self::Gst, self::Cbic, self::Nabard];
    }

    public function storageDirectory(): string
    {
        return match ($this) {
            self::IncomeTax => 'it',
            self::UserUpload => 'user-uploads',
            default => $this->value,
        };
    }
}
