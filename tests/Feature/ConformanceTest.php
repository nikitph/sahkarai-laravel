<?php

namespace Tests\Feature;

use App\Actions\Organizations\CreateOrganization;
use App\Events\ConformancePing;
use App\Jobs\RecordOrganizationActivity;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_dispatch_carries_the_current_organization(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $organization = app(CreateOrganization::class)->handle($owner, 'Acme');

        $this->actingAs($owner)->post(route('conformance.queue'))->assertOk();

        Queue::assertPushed(
            RecordOrganizationActivity::class,
            fn (RecordOrganizationActivity $job) => $job->organizationId === $organization->id,
        );
    }

    public function test_queued_work_establishes_and_clears_tenant_context(): void
    {
        $owner = User::factory()->create();
        $organization = app(CreateOrganization::class)->handle($owner, 'Acme');
        $context = app(TenantContext::class);

        (new RecordOrganizationActivity($organization->id, 'test'))->handle($context);

        $this->assertDatabaseHas('conformance_events', [
            'organization_id' => $organization->id,
            'kind' => 'queue_job',
            'detail' => 'test',
        ]);
        $this->assertFalse($context->check());
    }

    public function test_viewer_role_has_no_mutating_permissions(): void
    {
        $this->assertSame([], Role::Viewer->permissions());
    }

    public function test_broadcast_conformance_event_has_a_stable_channel_and_name(): void
    {
        $event = new ConformancePing('hello');

        $this->assertSame('conformance', $event->broadcastOn()[0]->name);
        $this->assertSame('ping', $event->broadcastAs());
    }

    public function test_sse_endpoint_is_configured_for_incremental_delivery(): void
    {
        $response = $this->get(route('conformance.sse'));

        $response->assertOk();
        $this->assertSame('text/event-stream; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('event: done', $response->streamedContent());
    }

    public function test_forwarded_https_requests_generate_secure_asset_urls(): void
    {
        $response = $this
            ->withHeaders([
                'X-Forwarded-Host' => 'app.example.test',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/');

        $response->assertOk();
        $response->assertSee('https://app.example.test/build/', false);
        $response->assertDontSee('http://app.example.test/build/', false);
    }
}
