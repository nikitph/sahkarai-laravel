<?php

namespace App\Actions\Billing;

use App\Actions\Credits\AdjustCredits;
use App\Enums\CreditReason;
use App\Enums\OrganizationSeatStatus;
use App\Enums\Tier;
use App\Models\Organization;
use App\Models\OrganizationSeat;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class ApplyOrganizationSubscriptionEntitlements
{
    public function __construct(private readonly AdjustCredits $credits) {}

    public function activate(Subscription $subscription, string $eventId, bool $grantCycleCredits): void
    {
        $organization = $subscription->organization()->with('owner')->firstOrFail();
        $this->withinOrganization($organization, function () use ($subscription, $organization, $eventId, $grantCycleCredits): void {
            OrganizationSeat::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'user_id' => $organization->owner_id],
                [
                    'assigned_by' => $subscription->purchaser_user_id,
                    'status' => OrganizationSeatStatus::Active,
                    'activated_at' => now(),
                    'revoked_at' => null,
                ],
            );

            OrganizationSeat::query()
                ->where('status', OrganizationSeatStatus::Active)
                ->with('user')
                ->each(function (OrganizationSeat $seat) use ($subscription, $eventId, $grantCycleCredits): void {
                    $user = $seat->user;
                    if (! $user) {
                        return;
                    }

                    $user->update(['tier' => $subscription->tier]);
                    if (! $grantCycleCredits || ! $subscription->tier->canChat()) {
                        return;
                    }

                    $this->resetAndGrantCredits($user, $subscription, $eventId);
                });
        });
    }

    public function deactivate(Subscription $subscription): void
    {
        $organization = $subscription->organization()->firstOrFail();
        $this->withinOrganization($organization, function (): void {
            OrganizationSeat::query()
                ->where('status', OrganizationSeatStatus::Active)
                ->with('user')
                ->each(function (OrganizationSeat $seat): void {
                    $seat->user?->update(['tier' => Tier::Free, 'credits_balance' => 0, 'credits_reset_at' => null]);
                    $seat->update(['status' => OrganizationSeatStatus::Revoked, 'revoked_at' => now()]);
                });
        });
    }

    public function grantSeatActivationCredits(User $user, Subscription $subscription, OrganizationSeat $seat): void
    {
        if (! $subscription->tier->canChat()) {
            return;
        }

        $this->resetAndGrantCredits($user, $subscription, "seat-{$seat->getKey()}");
    }

    private function resetAndGrantCredits(User $user, Subscription $subscription, string $key): void
    {
        $user->refresh();
        if ($user->credits_balance > 0) {
            $this->credits->handle(
                $user,
                -$user->credits_balance,
                CreditReason::Adjustment,
                "razorpay-org-cycle-expiry:{$key}:user:{$user->getKey()}",
                $subscription,
                ['kind' => 'unused_cycle_expiry'],
            );
        }

        $grant = (int) config("sahkarai.tiers.{$subscription->tier->value}.monthly_credits");
        $this->credits->handle(
            $user,
            $grant,
            CreditReason::GrantCycle,
            "razorpay-org-cycle:{$key}:user:{$user->getKey()}",
            $subscription,
        );
        $user->update(['credits_reset_at' => $subscription->current_period_end]);
    }

    private function withinOrganization(Organization $organization, callable $callback): void
    {
        $context = app(TenantContext::class);
        $previous = $context->check() ? $context->organization() : null;
        $context->set($organization);

        try {
            $callback();
        } finally {
            $previous ? $context->set($previous) : $context->clear();
        }
    }
}
