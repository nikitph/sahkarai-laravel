<?php

namespace App\Models;

use App\Enums\OrganizationSeatStatus;
use App\Models\Scopes\BelongsToOrganization;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $user_id
 * @property int|null $invitation_id
 * @property int|null $assigned_by
 * @property OrganizationSeatStatus $status
 * @property Carbon|null $activated_at
 * @property Carbon|null $revoked_at
 */
#[Fillable(['organization_id', 'user_id', 'invitation_id', 'assigned_by', 'status', 'activated_at', 'revoked_at'])]
#[ScopedBy([BelongsToOrganization::class])]
class OrganizationSeat extends Model
{
    protected static function booted(): void
    {
        static::creating(function (OrganizationSeat $seat): void {
            if (! $seat->organization_id) {
                $seat->organization()->associate(app(TenantContext::class)->organization());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => OrganizationSeatStatus::class,
            'activated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Invitation, $this> */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
