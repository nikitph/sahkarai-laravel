<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\CancelOrganizationInvitation;
use App\Actions\Organizations\InviteMember;
use App\Actions\Organizations\RemoveOrganizationMember;
use App\Actions\Organizations\UpdateOrganizationMemberRole;
use App\Enums\OrganizationSeatStatus;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(TenantContext $context): Response
    {
        $organization = $context->organization();
        $this->authorize('manageMembers', $organization);
        $subscription = $organization->subscription()->first();

        return Inertia::render('members/index', [
            'members' => $organization->members()->orderBy('name')->get(['users.id', 'name', 'email']),
            'invitations' => $organization->invitations()->whereNull('accepted_at')->latest()->get(['id', 'email', 'role', 'expires_at']),
            'roles' => collect(Role::cases())->reject(fn (Role $role) => $role === Role::Owner)->values(),
            'seats' => [
                'used' => $organization->seats()->whereIn('status', [OrganizationSeatStatus::Reserved, OrganizationSeatStatus::Active])->count(),
                'total' => $subscription ? $subscription->seat_quantity : 0,
                'tier' => $subscription ? $subscription->tier : null,
                'status' => $subscription ? $subscription->status : null,
            ],
        ]);
    }

    public function store(InviteMemberRequest $request, TenantContext $context, InviteMember $invite, Audit $audit): RedirectResponse
    {
        $data = $request->validated();
        $invitation = $invite->handle($context->organization(), $request->user(), $data['email'], Role::from($data['role']));
        $audit->record('member.invited', $invitation, ['email' => $invitation->email]);

        return back()->with('success', 'Invitation queued for delivery.');
    }

    public function update(UpdateMemberRoleRequest $request, User $member, TenantContext $context, UpdateOrganizationMemberRole $update, Audit $audit): RedirectResponse
    {
        $role = Role::from($request->validated('role'));
        $update->handle($context->organization(), $member, $role);
        $audit->record('member.role_updated', $member, ['role' => $role->value]);

        return back()->with('success', 'Member role updated.');
    }

    public function destroy(Request $request, User $member, TenantContext $context, RemoveOrganizationMember $remove, Audit $audit): RedirectResponse
    {
        $this->authorize('manageMembers', $context->organization());
        $remove->handle($context->organization(), $member);
        $audit->record('member.removed', $member, ['email' => $member->email]);

        return back()->with('success', 'Member access and seat were removed.');
    }

    public function destroyInvitation(Request $request, int $invitation, TenantContext $context, CancelOrganizationInvitation $cancel, Audit $audit): RedirectResponse
    {
        $this->authorize('manageMembers', $context->organization());
        $invitation = Invitation::query()->whereKey($invitation)->firstOrFail();
        $audit->record('member.invitation_cancelled', $invitation, ['email' => $invitation->email]);
        $cancel->handle($invitation);

        return back()->with('success', 'Invitation cancelled and seat released.');
    }
}
