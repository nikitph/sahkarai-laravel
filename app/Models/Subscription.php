<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $organization_id
 * @property int|null $purchaser_user_id
 * @property string|null $provider_subscription_id
 * @property Tier $tier
 * @property SubscriptionStatus $status
 * @property Tier|null $pending_tier
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $cancel_at
 * @property Carbon|null $cancelled_at
 * @property array<string, mixed>|null $provider_payload
 * @property int $seat_quantity
 * @property int|null $pending_seat_quantity
 * @property int $discount_basis_points
 * @property int|null $pending_discount_basis_points
 * @property int|null $unit_price
 * @property string|null $provider_offer_id
 * @property string|null $pending_provider_offer_id
 * @property-read User|null $user
 * @property-read Organization|null $organization
 * @property-read User|null $purchaser
 */
class Subscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tier' => Tier::class,
            'status' => SubscriptionStatus::class,
            'pending_tier' => Tier::class,
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'provider_payload' => 'array',
            'seat_quantity' => 'integer',
            'pending_seat_quantity' => 'integer',
            'discount_basis_points' => 'integer',
            'pending_discount_basis_points' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_user_id');
    }

    public function isOrganization(): bool
    {
        return $this->organization_id !== null;
    }
}
