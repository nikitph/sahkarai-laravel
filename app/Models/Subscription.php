<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $provider_subscription_id
 * @property Tier $tier
 * @property SubscriptionStatus $status
 * @property Tier|null $pending_tier
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $cancel_at
 * @property Carbon|null $cancelled_at
 * @property array<string, mixed>|null $provider_payload
 * @property-read User $user
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
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
