<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrganization
{
    public function handle(User $owner, string $name): Organization
    {
        return DB::transaction(function () use ($owner, $name): Organization {
            $organization = Organization::create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
                'owner_id' => $owner->getKey(),
            ]);

            $organization->members()->attach($owner, ['role' => Role::Owner->value]);
            $owner->forceFill(['current_organization_id' => $organization->getKey()])->save();

            return $organization;
        });
    }
}
