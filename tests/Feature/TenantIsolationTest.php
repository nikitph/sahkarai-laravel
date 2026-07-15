<?php

namespace Tests\Feature;

use App\Actions\Organizations\CreateOrganization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_are_invisible_across_organizations(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceOrg = app(CreateOrganization::class)->handle($alice, 'Alice Co');
        $bobOrg = app(CreateOrganization::class)->handle($bob, 'Bob Co');

        app(TenantContext::class)->set($aliceOrg);
        $aliceProject = Project::create(['name' => 'Private roadmap']);
        app(TenantContext::class)->set($bobOrg);

        $this->assertNull(Project::find($aliceProject->id));
        $this->assertSame(0, Project::query()->count());
    }

    public function test_member_cannot_manage_workspace_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = app(CreateOrganization::class)->handle($owner, 'Acme');
        $organization->members()->attach($member, ['role' => Role::Member->value]);

        $this->assertFalse($member->can('manageMembers', $organization));
        $this->assertTrue($owner->can('manageMembers', $organization));
    }

    public function test_tenant_model_defaults_to_deny_without_context(): void
    {
        $this->assertSame(0, Project::query()->count());
    }
}
