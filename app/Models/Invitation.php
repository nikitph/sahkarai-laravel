<?php

namespace App\Models;

use App\Models\Scopes\BelongsToOrganization;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
#[ScopedBy([BelongsToOrganization::class])]
class Invitation extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation): void {
            if (! $invitation->organization_id) {
                $invitation->organization()->associate(app(TenantContext::class)->organization());
            }
        });
    }

    protected function casts(): array
    {
        return ['role' => Role::class, 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasOne<OrganizationSeat, $this> */
    public function seat(): HasOne
    {
        return $this->hasOne(OrganizationSeat::class);
    }
}
