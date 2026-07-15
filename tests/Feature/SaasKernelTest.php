<?php

namespace Tests\Feature;

use App\Actions\Organizations\CreateOrganization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_use_the_reference_module_end_to_end(): void
    {
        $owner = User::factory()->create();
        app(CreateOrganization::class)->handle($owner, 'Acme');

        $this->actingAs($owner)
            ->get(route('projects.index'))
            ->assertOk();

        $this->actingAs($owner)
            ->post(route('projects.store'), ['name' => 'Launch plan', 'description' => 'Ship it.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', ['name' => 'Launch plan', 'organization_id' => $owner->current_organization_id]);
        $this->assertDatabaseHas('audit_events', ['event' => 'project.created', 'organization_id' => $owner->current_organization_id]);
    }

    public function test_member_cannot_open_member_administration(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = app(CreateOrganization::class)->handle($owner, 'Acme');
        $organization->members()->attach($member, ['role' => Role::Member->value]);
        $member->update(['current_organization_id' => $organization->id]);

        $this->actingAs($member)
            ->get(route('members.index'))
            ->assertForbidden();
    }
}
