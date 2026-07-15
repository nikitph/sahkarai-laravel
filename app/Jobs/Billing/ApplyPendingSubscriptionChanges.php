<?php

namespace App\Jobs\Billing;

use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use App\Models\NotificationDelivery;
use App\Models\ProductNotification;
use App\Models\Subscription;
use App\Notifications\Billing\BillingStatusMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ApplyPendingSubscriptionChanges implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Subscription::query()
            ->where(function ($query): void {
                $query->where(fn ($query) => $query->whereNotNull('pending_tier')->where('cancel_at', '<=', now()))
                    ->orWhere(fn ($query) => $query->where('status', SubscriptionStatus::Halted)->where('current_period_end', '<=', now()));
            })
            ->each(function (Subscription $subscription): void {
                DB::transaction(function () use ($subscription): void {
                    $subscription = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
                    $target = $subscription->status === SubscriptionStatus::Halted
                        ? Tier::Free
                        : ($subscription->pending_tier ?? Tier::Free);
                    $user = $subscription->user()->lockForUpdate()->firstOrFail();
                    $user->update(['tier' => $target, 'credits_balance' => $target === Tier::Tier2 ? $user->credits_balance : 0]);
                    $subscription->update([
                        'tier' => $target,
                        'status' => $target === Tier::Free ? SubscriptionStatus::Cancelled : SubscriptionStatus::Active,
                        'pending_tier' => null,
                        'cancel_at' => null,
                        'cancelled_at' => $target === Tier::Free ? now() : null,
                    ]);

                    $notification = ProductNotification::query()->firstOrCreate([
                        'dedupe_key' => "billing-transition:{$subscription->getKey()}:".now()->toDateString(),
                    ], [
                        'user_id' => $user->getKey(), 'type' => 'billing_downgraded', 'title' => 'Your plan has changed',
                        'body' => 'Your account is now on the '.str_replace('_', ' ', $target->value).' plan.', 'data' => [],
                    ]);
                    NotificationDelivery::create([
                        'product_notification_id' => $notification->getKey(), 'user_id' => $user->getKey(),
                        'channel' => 'in_app', 'status' => 'delivered', 'locale' => $user->locale, 'delivered_at' => now(),
                    ]);
                    $user->notify((new BillingStatusMail($notification->title, $notification->body))->locale($user->locale->value));
                    NotificationDelivery::create([
                        'product_notification_id' => $notification->getKey(), 'user_id' => $user->getKey(),
                        'channel' => 'email', 'status' => 'queued', 'locale' => $user->locale,
                    ]);
                });
            });
    }
}
