<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\Billing\BillingGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Notifications\Account\AccountDeletionScheduled;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => false,
            'status' => $request->session()->get('status'),
            'subscription' => $user->subscription,
            'creditLedger' => $user->tier->canChat()
                ? $user->creditLedger()->latest()->limit(20)->get(['id', 'amount', 'balance_after', 'reason', 'created_at'])
                : [],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, BillingGateway $gateway): RedirectResponse
    {
        $user = $request->user();

        $subscription = $user->subscription;
        if ($subscription?->provider_subscription_id) {
            $gateway->cancel($subscription, false);
        }

        $user->forceFill(['hard_delete_at' => now()->addDays(30)])->save();
        Notification::route('mail', [$user->email => $user->name])
            ->notify(new AccountDeletionScheduled($user->getKey()));
        $user->delete();
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
