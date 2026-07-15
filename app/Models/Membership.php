<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $organization_id
 * @property int $user_id
 * @property Role $role
 */
class Membership extends Pivot
{
    protected $table = 'organization_user';

    protected function casts(): array
    {
        return ['role' => Role::class];
    }
}
