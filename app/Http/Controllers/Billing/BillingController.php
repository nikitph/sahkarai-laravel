<?php

namespace App\Http\Controllers\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $subscription = $request->user()->subscription()->firstOrCreate([], ['tier' => Tier::Free, 'status' => SubscriptionStatus::Free]);
        $this->authorize('view', $subscription);

        return Inertia::render('billing/index', [
            'subscription' => $subscription,
            'plans' => config('sahkarai.tiers'),
            'razorpayKey' => config('sahkarai.razorpay.key_id'),
        ]);
    }

    public function subscribe(Request $request, BillingGateway $gateway): RedirectResponse
    {
        $validated = $request->validate(['tier' => ['required', Rule::enum(Tier::class), 'not_in:free']]);
        $tier = Tier::from($validated['tier']);
        $subscription = $request->user()->subscription()->firstOrCreate([], ['tier' => Tier::Free, 'status' => SubscriptionStatus::Free]);
        $this->authorize('update', $subscription);

        if ($subscription->provider_subscription_id) {
            $isDowngrade = $subscription->tier === Tier::Tier2 && $tier === Tier::Tier1;
            $transition = [
                'from' => $subscription->tier->value,
                'to' => $tier->value,
                'requested_at' => now()->toIso8601String(),
                'previous_period_start' => $subscription->current_period_start?->toIso8601String(),
                'previous_period_end' => $subscription->current_period_end?->toIso8601String(),
            ];
            $provider = $gateway->changePlan($subscription, $tier, $isDowngrade);
            $subscription->update($isDowngrade ? [
                'pending_tier' => $tier,
                'cancel_at' => $subscription->current_period_end,
                'provider_payload' => [...$provider, 'local_transition' => $transition],
            ] : [
                'pending_tier' => $tier,
                'provider_payload' => [...$provider, 'local_transition' => $transition],
            ]);
        } else {
            $provider = $gateway->createSubscription($request->user(), $tier);
            $subscription->update([
                'provider_subscription_id' => $provider['id'],
                'tier' => $tier,
                'status' => SubscriptionStatus::Pending,
                'provider_payload' => $provider,
            ]);
        }

        return back()->with('success', 'Your plan change is ready. Complete any payment requested by Razorpay.');
    }

    public function cancel(Request $request, BillingGateway $gateway): RedirectResponse
    {
        $subscription = $request->user()->subscription()->firstOrFail();
        $this->authorize('update', $subscription);
        abort_unless((bool) $subscription->provider_subscription_id, 422);
        $provider = $gateway->cancel($subscription);
        $subscription->update(['pending_tier' => Tier::Free, 'cancel_at' => $subscription->current_period_end, 'provider_payload' => $provider]);

        return back()->with('success', 'Cancellation scheduled for the end of the billing period.');
    }

    public function resume(Request $request, BillingGateway $gateway): RedirectResponse
    {
        $subscription = $request->user()->subscription()->firstOrFail();
        $this->authorize('update', $subscription);
        abort_unless($subscription->pending_tier !== null, 422);
        $provider = $gateway->changePlan($subscription, $subscription->tier, false);
        $subscription->update(['pending_tier' => null, 'cancel_at' => null, 'provider_payload' => $provider]);

        return back()->with('success', 'The scheduled plan change was cancelled.');
    }
}
