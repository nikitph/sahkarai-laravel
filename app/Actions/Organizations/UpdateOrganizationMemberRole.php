<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationMemberRole
{
    public function handle(Organization $organization, User $member, Role $role): void
    {
        if ($organization->owner_id === $member->getKey() || $role === Role::Owner) {
            throw ValidationException::withMessages(['role' => 'Ownership cannot be changed from member administration.']);
        }

        abort_unless($organization->members()->whereKey($member)->exists(), 404);
        $organization->members()->updateExistingPivot($member->getKey(), ['role' => $role->value]);
    }
}
