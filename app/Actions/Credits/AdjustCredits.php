<?php

namespace App\Actions\Credits;

use App\Enums\CreditReason;
use App\Models\CreditLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustCredits
{
    /** @param array<string, mixed> $metadata */
    public function handle(
        User $user,
        int $amount,
        CreditReason $reason,
        string $idempotencyKey,
        ?Model $subject = null,
        array $metadata = [],
    ): CreditLedger {
        return DB::transaction(function () use ($user, $amount, $reason, $idempotencyKey, $subject, $metadata): CreditLedger {
            $existing = CreditLedger::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $locked = User::query()->withTrashed()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $balance = $locked->credits_balance + $amount;
            if ($balance < 0) {
                throw ValidationException::withMessages(['credits' => 'no_credits_remaining']);
            }

            $locked->forceFill(['credits_balance' => $balance])->save();

            return CreditLedger::create([
                'user_id' => $locked->getKey(),
                'amount' => $amount,
                'balance_after' => $balance,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'metadata' => $metadata,
            ]);
        }, attempts: 3);
    }
}
