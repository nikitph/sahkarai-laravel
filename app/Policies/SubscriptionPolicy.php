<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function view(User $user, Subscription $subscription): bool
    {
        return $subscription->isOrganization()
            ? $subscription->organization !== null && $user->roleFor($subscription->organization) !== null
            : $subscription->user_id === $user->getKey();
    }

    public function update(User $user, Subscription $subscription): bool
    {
        if ($subscription->isOrganization()) {
            return $subscription->organization !== null
                && $user->hasPermission(Permission::ManageBilling, $subscription->organization);
        }

        return $this->view($user, $subscription) && ! $user->isAdmin();
    }
}
