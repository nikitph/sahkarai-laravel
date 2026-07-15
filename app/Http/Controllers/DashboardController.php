<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $context): Response
    {
        return Inertia::render('dashboard', [
            'stats' => [
                'projects' => Project::query()->count(),
                'members' => $context->organization()->members()->count(),
                'pendingInvitations' => $context->organization()->invitations()->whereNull('accepted_at')->count(),
            ],
            'recentProjects' => Project::query()->latest()->limit(5)->get(['id', 'name', 'status', 'created_at']),
            'recentActivity' => AuditEvent::query()->where('organization_id', $context->id())->latest()->limit(5)->get(),
        ]);
    }
}
