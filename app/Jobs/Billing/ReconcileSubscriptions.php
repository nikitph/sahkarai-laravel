<?php

namespace App\Jobs\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Models\OpsAlert;
use App\Models\ReconciliationDrift;
use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ReconcileSubscriptions implements ShouldQueue
{
    use Queueable;

    public function handle(BillingGateway $gateway): void
    {
        Subscription::query()->whereNotNull('provider_subscription_id')->each(function (Subscription $subscription) use ($gateway): void {
            try {
                $remote = $gateway->fetchSubscription($subscription);
                foreach (['status'] as $field) {
                    $local = $subscription->status->value;
                    if ((string) $local === (string) ($remote[$field] ?? '')) {
                        continue;
                    }
                    ReconciliationDrift::firstOrCreate([
                        'subscription_id' => $subscription->getKey(), 'field' => $field, 'resolved_at' => null,
                    ], ['local_value' => $local, 'provider_value' => $remote[$field] ?? null, 'detected_at' => now()]);
                    OpsAlert::firstOrCreate([
                        'type' => 'subscription_drift', 'resolved_at' => null, 'title' => "Subscription drift for user {$subscription->user_id}",
                    ], ['severity' => 'warning', 'details' => "{$field}: local={$local}, provider=".($remote[$field] ?? 'missing'), 'context' => ['subscription_id' => $subscription->getKey()]]);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
