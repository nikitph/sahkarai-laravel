<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\AcceptOrganizationInvitation;
use App\Models\Invitation;
use App\Models\Organization;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function accept(
        Request $request,
        Organization $organization,
        string $token,
        TenantContext $context,
        AcceptOrganizationInvitation $accept,
    ): RedirectResponse {
        $context->set($organization);
        $invitation = Invitation::query()->where('token', $token)->whereNull('accepted_at')->firstOrFail();
        abort_if($invitation->expires_at->isPast(), 410, 'This invitation has expired.');

        if (! $request->user()) {
            return redirect()->route('login')->with('status', 'Sign in with '.$invitation->email.' to accept your invitation.');
        }

        $accept->handle($invitation, $request->user());
        $context->clear();

        return redirect()->route('dashboard')->with('success', 'Welcome to '.$invitation->organization->name.'.');
    }
}
