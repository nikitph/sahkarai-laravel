<?php

namespace App\Models;

use App\Enums\CreditReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $user_id
 * @property int $amount
 * @property int $balance_after
 * @property CreditReason $reason
 * @property string $idempotency_key
 * @property array<string, mixed>|null $metadata
 */
class CreditLedger extends Model
{
    protected $table = 'credit_ledger';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['reason' => CreditReason::class, 'metadata' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Credit ledger entries are immutable.'));
        static::deleting(fn () => throw new \LogicException('Credit ledger entries are immutable.'));
    }
}
