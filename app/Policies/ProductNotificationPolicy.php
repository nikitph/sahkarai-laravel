<?php

namespace App\Policies;

use App\Models\ProductNotification;
use App\Models\User;

class ProductNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tier->canReceiveNotifications() && ! $user->isAdmin();
    }

    public function update(User $user, ProductNotification $notification): bool
    {
        return $this->viewAny($user) && $notification->user_id === $user->getKey();
    }
}
