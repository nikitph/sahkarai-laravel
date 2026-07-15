<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class ProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        $organization = app(TenantContext::class)->organization();

        return $user->organizations()->whereKey($organization->getKey())->exists() ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageProjects, app(TenantContext::class)->organization());
    }

    public function update(User $user, Project $project): bool
    {
        return $this->create($user) && $project->organization_id === app(TenantContext::class)->id();
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
