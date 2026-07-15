<?php

namespace App\Http\Controllers\Ops;

use App\Enums\RegulatorySource;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\DocumentVersion;
use App\Models\Interpretation;
use App\Models\IssueReport;
use App\Models\OpsAlert;
use App\Models\PollRun;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpsDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('ops/dashboard', [
            'sources' => collect(RegulatorySource::cases())->map(fn (RegulatorySource $source) => [
                'source' => $source->value,
                'last_success' => PollRun::query()->where('source', $source)->whereIn('status', ['ok', 'partial'])->latest('completed_at')->first(),
                'last_failure' => PollRun::query()->where('source', $source)->where('status', 'failed')->latest('completed_at')->first(),
            ]),
            'counts' => [
                'extractionFailures' => DocumentVersion::query()->where('status', 'extraction_failed')->count(),
                'interpretationFailures' => Interpretation::query()->where('status', 'failed')->count(),
                'openIssues' => IssueReport::query()->where('status', 'open')->count(),
                'openAlerts' => OpsAlert::query()->whereNull('resolved_at')->count(),
                'totalChats' => Chat::query()->count(),
            ],
            'recentChatMetadata' => Chat::query()->latest()->limit(20)->get([
                'id', 'user_id', 'document_version_id', 'status', 'created_at',
            ]),
            'issues' => IssueReport::query()->with(['user:id,name,email', 'interpretation.version.document:id,title'])->latest()->limit(25)->get(),
            'alerts' => OpsAlert::query()->whereNull('resolved_at')->latest()->limit(25)->get(),
            'users' => User::query()->when($request->string('q')->toString(), function ($query, string $q): void {
                $query->where(fn ($query) => $query->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
            })->limit(20)->get(['id', 'name', 'email', 'tier', 'role', 'created_at']),
        ]);
    }
}
