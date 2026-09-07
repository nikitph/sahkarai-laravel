<?php

namespace App\Actions\Billing;

use App\Actions\Organizations\CreateOrganization;
use App\Contracts\Billing\BillingGateway;
use App\Enums\OrganizationSeatStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use App\Models\Organization;
use App\Models\OrganizationSeat;
use App\Models\Permission;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Billing\OrganizationSeatPricing;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PurchaseOrganizationSubscription
{
    public function __construct(
        private readonly CreateOrganization $createOrganization,
        private readonly OrganizationSeatPricing $pricing,
        private readonly BillingGateway $gateway,
    ) {}

    /** @return array{subscription: Subscription, organization: Organization, quote: array<string, int|string>} */
    public function handle(User $purchaser, ?string $organizationName, Tier $tier, int $seats): array
    {
        if (! config('sahkarai.razorpay.organization_billing.enabled')) {
            throw new RuntimeException('Organization billing is not enabled.');
        }

        $quote = $this->pricing->quote($tier, $seats);
        $organization = $purchaser->currentOrganization;

        if (! $organization) {
            $organization = $this->createOrganization->handle($purchaser, trim((string) $organizationName));
        } elseif (! $purchaser->hasPermission(Permission::ManageBilling, $organization)) {
            throw new AuthorizationException;
        }

        if ($purchaser->subscription()->whereNotNull('provider_subscription_id')->exists()) {
            throw ValidationException::withMessages([
                'organization_name' => 'Individual-to-organization subscription transitions are not available yet.',
            ]);
        }

        $existing = $organization->subscription()->first();
        if ($existing?->provider_subscription_id) {
            throw ValidationException::withMessages(['seats' => 'This organization already has a subscription.']);
        }

        $context = app(TenantContext::class);
        $previous = $context->check() ? $context->organization() : null;
        $context->set($organization);

        try {
            $subscription = DB::transaction(function () use ($organization, $purchaser, $tier, $quote): Subscription {
                $subscription = Subscription::query()->updateOrCreate(
                    ['organization_id' => $organization->getKey()],
                    [
                        'user_id' => null,
                        'purchaser_user_id' => $purchaser->getKey(),
                        'tier' => Tier::Free,
                        'pending_tier' => $tier,
                        'status' => SubscriptionStatus::Pending,
                        'seat_quantity' => $quote['seats'],
                        'discount_basis_points' => $quote['discount_basis_points'],
                        'unit_price' => $quote['unit_price'],
                        'provider_offer_id' => $quote['offer_id'],
                    ],
                );

                OrganizationSeat::query()->updateOrCreate(
                    ['organization_id' => $organization->getKey(), 'user_id' => $organization->owner_id],
                    ['assigned_by' => $purchaser->getKey(), 'status' => OrganizationSeatStatus::Reserved, 'revoked_at' => null],
                );

                return $subscription;
            });

            $provider = $this->gateway->createOrganizationSubscription(
                $organization,
                $purchaser,
                $tier,
                $seats,
                (string) $quote['offer_id'],
            );
            $providerId = (string) ($provider['id'] ?? '');
            if ($providerId === '') {
                throw new RuntimeException('Razorpay did not return a subscription identifier.');
            }

            $subscription->update([
                'provider_subscription_id' => $providerId,
                'provider_payload' => $provider,
            ]);

            return ['subscription' => $subscription->refresh(), 'organization' => $organization, 'quote' => $quote];
        } finally {
            $previous ? $context->set($previous) : $context->clear();
        }
    }
}
