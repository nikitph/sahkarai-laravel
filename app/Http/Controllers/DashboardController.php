<?php

namespace App\Http\Controllers;

use App\Models\RegulatoryDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return to_route('ops.dashboard');
        }

        return Inertia::render('dashboard', [
            'stats' => [
                'documents' => RegulatoryDocument::query()->count(),
                'newThisWeek' => RegulatoryDocument::query()->where('created_at', '>=', now()->subWeek())->count(),
                'unreadNotifications' => $user->productNotifications()->whereNull('read_at')->count(),
                'credits' => $user->credits_balance,
            ],
            'recentDocuments' => RegulatoryDocument::query()
                ->with('latestVersion')
                ->latest('published_at')
                ->limit(6)
                ->get(['id', 'title', 'source', 'document_type', 'published_at', 'applicability']),
            'subscription' => $user->subscription,
        ]);
    }
}
