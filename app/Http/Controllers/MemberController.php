<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\InviteMember;
use App\Http\Requests\InviteMemberRequest;
use App\Models\Role;
use App\Support\Audit\Audit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(TenantContext $context): Response
    {
        $organization = $context->organization();
        $this->authorize('manageMembers', $organization);

        return Inertia::render('members/index', [
            'members' => $organization->members()->orderBy('name')->get(['users.id', 'name', 'email']),
            'invitations' => $organization->invitations()->whereNull('accepted_at')->latest()->get(['id', 'email', 'role', 'expires_at']),
            'roles' => collect(Role::cases())->reject(fn (Role $role) => $role === Role::Owner)->values(),
        ]);
    }

    public function store(InviteMemberRequest $request, TenantContext $context, InviteMember $invite, Audit $audit): RedirectResponse
    {
        $data = $request->validated();
        $invitation = $invite->handle($context->organization(), $request->user(), $data['email'], Role::from($data['role']));
        $audit->record('member.invited', $invitation, ['email' => $invitation->email]);

        return back()->with('success', 'Invitation queued for delivery.');
    }
}
