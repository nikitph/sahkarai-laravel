<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $email
 * @property Role $role
 * @property string $token
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property-read Organization $organization
 */
#[Fillable(['organization_id', 'email', 'role', 'token', 'invited_by', 'expires_at', 'accepted_at'])]
class Invitation extends Model
{
    protected function casts(): array
    {
        return ['role' => Role::class, 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
