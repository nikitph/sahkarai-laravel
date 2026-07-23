<?php

namespace App\Http\Controllers\Archive;

use App\Enums\SupportedLocale;
use App\Http\Controllers\Controller;
use App\Models\Interpretation;
use App\Models\IssueReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IssueReportController extends Controller
{
    public function store(Request $request, Interpretation $interpretation): RedirectResponse
    {
        $this->authorize('create', IssueReport::class);
        $interpretation->load('version.document');
        $this->authorize('view', $interpretation->version->document);
        abort_unless(in_array($interpretation->status, ['published', 'partial'], true), 404);
        $validated = $request->validate([
            'category' => ['nullable', 'in:inaccurate,mistranslation,missing_takeaway,wrong_applicability,other'],
            'locale' => ['required', Rule::enum(SupportedLocale::class)],
            'description' => ['required', 'string', 'max:3000'],
        ]);
        $interpretation->issueReports()->create([
            'user_id' => $request->user()->getKey(),
            'document_version_id' => $interpretation->document_version_id,
            'locale' => $validated['locale'],
            'category' => $validated['category'] ?? null,
            'details' => $validated['description'],
        ]);

        return back()->with('success', 'Thank you. The interpretation was sent for review.');
    }
}
