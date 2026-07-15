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
            'status' => ['required', 'in:open,investigating,resolved,dismissed'],
            'resolution' => ['nullable', 'string', 'max:3000'],
        ]);
        $issue->update([...$validated, 'resolved_at' => in_array($validated['status'], ['resolved', 'dismissed'], true) ? now() : null]);

        return back()->with('success', 'Issue updated.');
    }
}
