<?php

namespace App\Http\Controllers\Ops;

use App\Actions\Credits\AdjustCredits;
use App\Enums\CreditReason;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreditAdjustmentController extends Controller
{
    public function store(Request $request, User $user, AdjustCredits $credits): RedirectResponse
    {
        abort_if($user->isAdmin(), 422, 'Admin accounts do not receive product credits.');
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'between:-10000,10000', Rule::notIn([0])],
            'reference' => ['required', 'string', 'max:100'],
            'note' => ['required', 'string', 'max:1000'],
        ]);
        $credits->handle(
            $user,
            $validated['amount'],
            CreditReason::Adjustment,
            'manual-adjustment:'.$validated['reference'],
            metadata: ['note' => $validated['note'], 'admin_id' => $request->user()->getKey()],
        );

        return back()->with('success', 'Credit adjustment recorded.');
    }
}
