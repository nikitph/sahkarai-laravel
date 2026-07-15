<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\IssueReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IssueTriageController extends Controller
{
    public function update(Request $request, IssueReport $issue): RedirectResponse
    {
        $validated = $request->validate([
            'triage_status' => ['required', 'in:triaged,resolved,wontfix'],
            'internal_note' => ['required', 'string', 'max:3000'],
        ]);
        $issue->update([
            'status' => $validated['triage_status'],
            'internal_note' => $validated['internal_note'],
            'triaged_by' => $request->user()->getKey(),
            'triaged_at' => now(),
            'resolved_at' => in_array($validated['triage_status'], ['resolved', 'wontfix'], true) ? now() : null,
        ]);

        return back()->with('success', 'Issue updated.');
    }
}
