<?php

namespace App\Actions\Organizations;

use App\Enums\Tier;
use App\Models\Organization;
use App\Models\OrganizationSeat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveOrganizationMember
{
    public function handle(Organization $organization, User $member): void
    {
        if ($organization->owner_id === $member->getKey()) {
            throw ValidationException::withMessages(['member' => 'The organization owner cannot be removed.']);
        }

        DB::transaction(function () use ($organization, $member): void {
            $organization->members()->whereKey($member)->lockForUpdate()->firstOrFail();
            OrganizationSeat::query()->where('user_id', $member->getKey())->delete();
            $organization->members()->detach($member);
            $member->update(['tier' => Tier::Free, 'credits_balance' => 0, 'credits_reset_at' => null]);
            if ($member->current_organization_id === $organization->getKey()) {
                $member->update(['current_organization_id' => null]);
            }
        }, attempts: 3);
    }
}
