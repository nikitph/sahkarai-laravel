<?php

namespace App\Enums;

enum OrganizationSeatStatus: string
{
    case Reserved = 'reserved';
    case Active = 'active';
    case Revoked = 'revoked';
}
