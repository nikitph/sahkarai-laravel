<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function view(User $user, Subscription $subscription): bool
    {
        return $subscription->user_id === $user->getKey();
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $this->view($user, $subscription) && ! $user->isAdmin();
    }
}
