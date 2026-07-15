<?php

namespace App\Policies;

use App\Models\Chat;
use App\Models\User;

class ChatPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canUseChat() && ! $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Chat $chat): bool
    {
        return $this->viewAny($user) && $chat->user_id === $user->getKey();
    }

    public function update(User $user, Chat $chat): bool
    {
        return $this->view($user, $chat);
    }
}
