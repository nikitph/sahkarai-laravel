<?php

namespace App\Services\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Enums\Tier;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class RazorpayGateway implements BillingGateway
{
    /** @return array<string, mixed> */
    public function createSubscription(User $user, Tier $tier): array
    {
        return $this->client()->post('/subscriptions', [
            'plan_id' => config("sahkarai.razorpay.plans.{$tier->value}"),
            'total_count' => 120,
            'customer_notify' => 1,
            'notes' => ['user_id' => (string) $user->getKey(), 'tier' => $tier->value],
        ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function changePlan(Subscription $subscription, Tier $tier, bool $atCycleEnd): array
    {
        return $this->client()->patch("/subscriptions/{$subscription->provider_subscription_id}", [
            'plan_id' => config("sahkarai.razorpay.plans.{$tier->value}"),
            'schedule_change_at' => $atCycleEnd ? 'cycle_end' : 'now',
            'customer_notify' => 1,
        ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function cancel(Subscription $subscription, bool $atCycleEnd = true): array
    {
        return $this->client()->post("/subscriptions/{$subscription->provider_subscription_id}/cancel", [
            'cancel_at_cycle_end' => $atCycleEnd,
        ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function fetchSubscription(Subscription $subscription): array
    {
        return $this->client()->get("/subscriptions/{$subscription->provider_subscription_id}")->throw()->json();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('sahkarai.razorpay.base_url'))
            ->withBasicAuth((string) config('sahkarai.razorpay.key_id'), (string) config('sahkarai.razorpay.key_secret'))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500);
    }
}
