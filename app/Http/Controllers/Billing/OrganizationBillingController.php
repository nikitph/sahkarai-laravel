<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\PurchaseOrganizationSubscription;
use App\Enums\Tier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PurchaseOrganizationSubscriptionRequest;
use App\Models\Subscription;
use App\Support\Audit\Audit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationBillingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(config('sahkarai.razorpay.organization_billing.enabled'), 404);

        $organization = $request->user()->currentOrganization;
        $subscription = $organization?->subscription;
        if ($subscription) {
            $this->authorize('view', $subscription);
        }

        $plans = config('sahkarai.tiers', []);
        $plans = is_array($plans) ? $plans : [];
        unset($plans['free']);
        $configuredDiscounts = config('sahkarai.razorpay.organization_billing.discounts', []);
        $discounts = [];
        if (is_array($configuredDiscounts)) {
            foreach ($configuredDiscounts as $band) {
                if (is_array($band)) {
                    $discounts[] = [
                        'min' => $band['min'],
                        'max' => $band['max'],
                        'percent' => $band['basis_points'] / 100,
                    ];
                }
            }
        }

        return Inertia::render('billing/team', [
            'organization' => $organization,
            'canManageSeats' => $organization ? $request->user()->can('manageMembers', $organization) : false,
            'subscription' => $subscription,
            'plans' => $plans,
            'discounts' => $discounts,
            'autoApprove' => ! config('sahkarai.razorpay.organization_billing.razorpay_enabled'),
            'checkout' => $request->session()->pull('razorpay_team_checkout')
                ?? ($subscription?->status->value === 'pending' && $subscription->provider_subscription_id
                    ? $this->checkoutPayload($request, $subscription)
                    : null),
        ]);
    }

    public function store(
        PurchaseOrganizationSubscriptionRequest $request,
        PurchaseOrganizationSubscription $purchase,
        Audit $audit,
        TenantContext $context,
    ): RedirectResponse {
        $data = $request->validated();
        $result = $purchase->handle(
            $request->user(),
            $data['organization_name'] ?? null,
            Tier::from($data['tier']),
            (int) $data['seats'],
        );

        if ($result['subscription']->provider_subscription_id) {
            $request->session()->put('razorpay_team_checkout', $this->checkoutPayload($request, $result['subscription']));
        }
        $context->set($result['organization']);
        try {
            $audit->record('organization.subscription.created', $result['subscription'], [
                'tier' => $data['tier'],
                'seats' => (int) $data['seats'],
                'discount_basis_points' => $result['quote']['discount_basis_points'],
            ]);
        } finally {
            $context->clear();
        }

        $message = $result['subscription']->status->value === 'active'
            ? 'Your organization plan and seats are active.'
            : 'Complete checkout to activate your organization seats.';

        return redirect()->route('billing.team.index')->with('success', $message);
    }

    /** @return array<string, int|string> */
    private function checkoutPayload(Request $request, Subscription $subscription): array
    {
        return [
            'key' => (string) config('sahkarai.razorpay.key_id'),
            'subscription_id' => (string) $subscription->provider_subscription_id,
            'tier' => ($subscription->pending_tier ?? $subscription->tier)->value,
            'seats' => $subscription->seat_quantity,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
        ];
    }
}
