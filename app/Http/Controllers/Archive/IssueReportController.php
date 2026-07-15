<?php

namespace App\Http\Controllers\Archive;

use App\Http\Controllers\Controller;
use App\Models\Interpretation;
use App\Models\IssueReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IssueReportController extends Controller
{
    public function store(Request $request, Interpretation $interpretation): RedirectResponse
    {
        $this->authorize('create', IssueReport::class);
        $validated = $request->validate([
            'category' => ['required', 'in:incorrect,unclear,missing,translation,other'],
            'details' => ['required', 'string', 'max:3000'],
        ]);
        $interpretation->issueReports()->create([...$validated, 'user_id' => $request->user()->getKey()]);

        return back()->with('success', 'Thank you. The interpretation was sent for review.');
    }
}
