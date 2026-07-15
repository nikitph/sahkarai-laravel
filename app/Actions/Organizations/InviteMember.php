<?php

namespace App\Actions\Organizations;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteMember
{
    public function handle(Organization $organization, User $actor, string $email, Role $role): Invitation
    {
        $invitation = Invitation::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'email' => Str::lower($email), 'accepted_at' => null],
            ['role' => $role, 'token' => hash('sha256', Str::random(64)), 'invited_by' => $actor->getKey(), 'expires_at' => now()->addDays(7)],
        );

        Notification::route('mail', $invitation->email)->notify(new OrganizationInvitation($invitation));

        return $invitation;
    }
}
