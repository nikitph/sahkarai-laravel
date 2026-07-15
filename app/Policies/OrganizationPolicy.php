<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;

class OrganizationPolicy
{
    public function update(User $user, Organization $organization): bool
    {
        return $user->hasPermission(Permission::ManageOrganization, $organization);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->hasPermission(Permission::ManageMembers, $organization);
    }
}
