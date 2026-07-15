<?php

namespace App\Models;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),
            self::Admin => [Permission::ManageOrganization, Permission::ManageMembers, Permission::ViewAuditLog, Permission::ManageProjects],
            self::Member => [Permission::ManageProjects],
            self::Viewer => [],
        };
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
