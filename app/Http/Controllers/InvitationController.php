<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvitationController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::query()->where('token', $token)->whereNull('accepted_at')->firstOrFail();
        abort_if($invitation->expires_at->isPast(), 410, 'This invitation has expired.');

        if (! $request->user()) {
            return redirect()->route('login')->with('status', 'Sign in with '.$invitation->email.' to accept your invitation.');
        }

        abort_unless(strcasecmp($request->user()->email, $invitation->email) === 0, 403);

        DB::transaction(function () use ($request, $invitation): void {
            $invitation->organization->members()->syncWithoutDetaching([
                $request->user()->getKey() => ['role' => $invitation->role->value],
            ]);
            $invitation->update(['accepted_at' => now()]);
            $request->user()->update(['current_organization_id' => $invitation->organization_id]);
        });

        return redirect()->route('dashboard')->with('success', 'Welcome to '.$invitation->organization->name.'.');
    }
}
