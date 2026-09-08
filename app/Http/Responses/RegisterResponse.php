<?php

namespace App\Http\Responses;

use App\Enums\SubscriptionStatus;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        if ($request->input('account_type') === 'organization') {
            $subscription = $request->user()?->currentOrganization?->subscription;

            return $subscription?->status === SubscriptionStatus::Active
                ? redirect()->route('members.index')
                : redirect()->route('billing.team.index');
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}
