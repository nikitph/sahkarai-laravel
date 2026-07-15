<?php

namespace App\Enums;

enum UserRole: string
{
    case IndividualMember = 'individual_member';
    case SaasAdmin = 'saas_admin';
}
