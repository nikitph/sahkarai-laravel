<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationSeatStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationSeat;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteMember
{
    public function handle(Organization $organization, User $actor, string $email, Role $role): Invitation
    {
        $normalizedEmail = Str::lower($email);
        $invitation = DB::transaction(function () use ($organization, $actor, $normalizedEmail, $role): Invitation {
            $subscription = $organization->subscription()->lockForUpdate()->first();
            if (! $subscription || $subscription->status !== SubscriptionStatus::Active) {
                throw ValidationException::withMessages(['email' => 'An active organization subscription is required before inviting members.']);
            }

            if ($organization->members()->whereRaw('LOWER(users.email) = ?', [$normalizedEmail])->exists()) {
                throw ValidationException::withMessages(['email' => 'This person is already a member.']);
            }

            $invitation = Invitation::query()->where('email', $normalizedEmail)->whereNull('accepted_at')->lockForUpdate()->first();
            $reservesExistingSeat = $invitation?->seat()->where('status', OrganizationSeatStatus::Reserved)->exists() ?? false;
            $usedSeats = OrganizationSeat::query()->whereIn('status', [OrganizationSeatStatus::Reserved, OrganizationSeatStatus::Active])->count();
            if (! $reservesExistingSeat && $usedSeats >= $subscription->seat_quantity) {
                throw ValidationException::withMessages(['email' => 'All purchased seats are already assigned or reserved.']);
            }

            $invitation ??= new Invitation(['organization_id' => $organization->getKey(), 'email' => $normalizedEmail]);
            $invitation->fill([
                'role' => $role,
                'token' => hash('sha256', Str::random(64)),
                'invited_by' => $actor->getKey(),
                'expires_at' => now()->addDays(7),
            ])->save();

            OrganizationSeat::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'invitation_id' => $invitation->getKey()],
                ['assigned_by' => $actor->getKey(), 'status' => OrganizationSeatStatus::Reserved, 'revoked_at' => null],
            );

            return $invitation;
        }, attempts: 3);

        Notification::route('mail', $invitation->email)->notify(new OrganizationInvitation(
            $invitation->organization_id,
            $organization->name,
            $invitation->role,
            $invitation->token,
        ));

        return $invitation;
    }
}
