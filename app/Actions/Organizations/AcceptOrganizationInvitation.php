<?php

namespace App\Actions\Organizations;

use App\Actions\Billing\ApplyOrganizationSubscriptionEntitlements;
use App\Enums\OrganizationSeatStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use App\Models\Invitation;
use App\Models\OrganizationSeat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptOrganizationInvitation
{
    public function __construct(private readonly ApplyOrganizationSubscriptionEntitlements $entitlements) {}

    public function handle(Invitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $invitation = Invitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();
            if ($invitation->accepted_at) {
                return;
            }
            if ($invitation->expires_at->isPast()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation has expired.']);
            }
            if (strcasecmp($user->email, $invitation->email) !== 0) {
                throw ValidationException::withMessages(['invitation' => 'Sign in with the invited email address.']);
            }
            if ($user->tier !== Tier::Free || $user->subscription()->whereNotNull('provider_subscription_id')->exists()) {
                throw ValidationException::withMessages(['invitation' => 'Paid individual subscription transitions are not available yet.']);
            }

            $subscription = $invitation->organization->subscription()->lockForUpdate()->first();
            if (! $subscription || $subscription->status !== SubscriptionStatus::Active) {
                throw ValidationException::withMessages(['invitation' => 'This organization does not have active seat access.']);
            }

            $seat = OrganizationSeat::query()
                ->where('invitation_id', $invitation->getKey())
                ->where('status', OrganizationSeatStatus::Reserved)
                ->lockForUpdate()
                ->firstOrFail();

            $invitation->organization->members()->syncWithoutDetaching([
                $user->getKey() => ['role' => $invitation->role->value],
            ]);
            $invitation->update(['accepted_at' => now()]);
            $seat->update([
                'user_id' => $user->getKey(),
                'status' => OrganizationSeatStatus::Active,
                'activated_at' => now(),
            ]);
            $user->update([
                'current_organization_id' => $invitation->organization_id,
                'tier' => $subscription->tier,
            ]);
            $this->entitlements->grantSeatActivationCredits($user, $subscription, $seat);
        }, attempts: 3);
    }
}
