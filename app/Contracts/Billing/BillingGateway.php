<?php

namespace App\Contracts\Billing;

use App\Enums\Tier;
use App\Models\Subscription;
use App\Models\User;

interface BillingGateway
{
    /** @return array<string, mixed> */
    public function createSubscription(User $user, Tier $tier): array;

    /** @return array<string, mixed> */
    public function changePlan(Subscription $subscription, Tier $tier, bool $atCycleEnd): array;

    /** @return array<string, mixed> */
    public function cancel(Subscription $subscription, bool $atCycleEnd = true): array;

    /** @return array<string, mixed> */
    public function fetchSubscription(Subscription $subscription): array;
}
