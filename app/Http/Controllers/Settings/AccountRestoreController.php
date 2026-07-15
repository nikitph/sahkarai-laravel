<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountRestoreController extends Controller
{
    public function __invoke(Request $request, int $user): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $account = User::withTrashed()->findOrFail($user);
        abort_unless($account->trashed() && $account->hard_delete_at?->isFuture(), 410, 'The restoration window has expired.');
        $account->restore();
        $account->update(['hard_delete_at' => null]);
        Auth::login($account);

        return to_route('dashboard')->with('success', 'Your account has been restored.');
    }
}
