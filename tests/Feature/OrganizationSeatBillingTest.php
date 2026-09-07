<?php

namespace Tests\Feature;

use App\Actions\Billing\ProcessRazorpayWebhook;
use App\Actions\Organizations\CreateOrganization;
use App\Contracts\Billing\BillingGateway;
use App\Enums\OrganizationSeatStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationSeat;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\RazorpayGateway;
use App\Support\Billing\OrganizationSeatPricing;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class OrganizationSeatBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sahkarai.razorpay.key_id' => 'rzp_test_team',
            'sahkarai.razorpay.organization_billing.enabled' => true,
            'sahkarai.razorpay.organization_billing.razorpay_enabled' => false,
            'sahkarai.razorpay.organization_billing.discounts.0.offer_id' => 'offer_5',
            'sahkarai.razorpay.organization_billing.discounts.1.offer_id' => 'offer_10',
            'sahkarai.razorpay.organization_billing.discounts.2.offer_id' => 'offer_15',
            'sahkarai.razorpay.organization_billing.discounts.3.offer_id' => 'offer_20',
            'sahkarai.razorpay.organization_billing.discounts.4.offer_id' => 'offer_25',
        ]);
    }

    public function test_discount_schedule_is_applied_per_seat_for_every_band(): void
    {
        $pricing = app(OrganizationSeatPricing::class);

        foreach ([[2, 500], [5, 500], [6, 1000], [10, 1000], [11, 1500], [15, 1500], [16, 2000], [20, 2000], [21, 2500], [25, 2500]] as [$seats, $basisPoints]) {
            $quote = $pricing->quote(Tier::Tier2, $seats);
            $this->assertSame($basisPoints, $quote['discount_basis_points']);
            $this->assertSame(149900 * $seats, $quote['subtotal']);
            $this->assertSame($quote['subtotal'] - intdiv($quote['subtotal'] * $basisPoints, 10_000), $quote['total']);
        }
    }

    public function test_owner_can_start_team_checkout_without_receiving_early_access(): void
    {
        config(['sahkarai.razorpay.organization_billing.razorpay_enabled' => true]);
        $owner = User::factory()->create();
        $gateway = Mockery::mock(BillingGateway::class);
        $gateway->shouldReceive('createOrganizationSubscription')
            ->once()
            ->withArgs(fn ($organization, User $purchaser, Tier $tier, int $quantity, string $offer): bool => $organization->name === 'Acme Cooperative'
                && $purchaser->is($owner)
                && $tier === Tier::Tier2
                && $quantity === 6
                && $offer === 'offer_10')
            ->andReturn(['id' => 'sub_team_1', 'status' => 'created']);
        $this->app->instance(BillingGateway::class, $gateway);

        $this->actingAs($owner)->post(route('billing.team.store'), [
            'organization_name' => 'Acme Cooperative',
            'tier' => Tier::Tier2->value,
            'seats' => 6,
        ])->assertRedirect(route('billing.team.index'))->assertSessionHas('razorpay_team_checkout');

        $subscription = Subscription::query()->where('provider_subscription_id', 'sub_team_1')->firstOrFail();
        $this->assertNull($subscription->user_id);
        $this->assertSame(6, $subscription->seat_quantity);
        $this->assertSame(1000, $subscription->discount_basis_points);
        $this->assertSame(Tier::Free, $owner->refresh()->tier);

        app(TenantContext::class)->set($subscription->organization);
        $this->assertDatabaseHas('organization_seats', [
            'organization_id' => $subscription->organization_id,
            'user_id' => $owner->id,
            'status' => OrganizationSeatStatus::Reserved->value,
        ]);
    }

    public function test_local_approval_activates_the_plan_owner_seat_and_credits_without_razorpay(): void
    {
        $owner = User::factory()->create();
        $gateway = Mockery::mock(BillingGateway::class);
        $gateway->shouldNotReceive('createOrganizationSubscription');
        $this->app->instance(BillingGateway::class, $gateway);

        $this->actingAs($owner)->post(route('billing.team.store'), [
            'organization_name' => 'Local Approval Co',
            'tier' => Tier::Tier2->value,
            'seats' => 10,
        ])->assertRedirect(route('billing.team.index'))
            ->assertSessionMissing('razorpay_team_checkout');

        $subscription = Subscription::query()->where('purchaser_user_id', $owner->id)->firstOrFail();
        $this->assertSame('local', $subscription->provider);
        $this->assertNull($subscription->provider_subscription_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(Tier::Tier2, $subscription->tier);
        $this->assertSame(10, $subscription->seat_quantity);
        $this->assertSame(1000, $subscription->discount_basis_points);
        $this->assertSame(Tier::Tier2, $owner->refresh()->tier);
        $this->assertSame(200, $owner->credits_balance);

        app(TenantContext::class)->set($subscription->organization);
        $this->assertSame(OrganizationSeatStatus::Active, OrganizationSeat::query()->firstOrFail()->status);
        $this->assertDatabaseHas('credit_ledger', [
            'user_id' => $owner->id,
            'amount' => 200,
            'reason' => 'grant_cycle',
        ]);
    }

    public function test_team_billing_page_preserves_shared_organization_navigation_props(): void
    {
        [$owner, $organization] = $this->organization('Navigation Co');

        $this->actingAs($owner)->get(route('billing.team.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/team')
                ->where('billingOrganization.id', $organization->id)
                ->where('organization.current.id', $organization->id)
                ->where('organization.permissions', [
                    'organization.manage',
                    'billing.manage',
                    'members.manage',
                    'audit.view',
                    'projects.manage',
                ]));
    }

    public function test_razorpay_receives_native_quantity_and_discount_offer(): void
    {
        config(['sahkarai.razorpay.plans.tier_3' => 'plan_team_tier_3']);
        Http::fake(['*/subscriptions' => Http::response(['id' => 'sub_provider_team'])]);
        [$owner, $organization] = $this->organization('Provider Co');

        app(RazorpayGateway::class)->createOrganizationSubscription(
            $organization,
            $owner,
            Tier::Tier3,
            21,
            'offer_25',
        );

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.razorpay.com/v1/subscriptions'
            && $request['plan_id'] === 'plan_team_tier_3'
            && $request['quantity'] === 21
            && $request['offer_id'] === 'offer_25'
            && $request['notes']['organization_id'] === (string) $organization->id);
    }

    public function test_signed_lifecycle_events_activate_owner_and_grant_credits_once(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->organization('Lifecycle Co');
        $subscription = Subscription::create([
            'user_id' => null,
            'organization_id' => $organization->id,
            'purchaser_user_id' => $owner->id,
            'provider_subscription_id' => 'sub_team_lifecycle',
            'tier' => Tier::Free,
            'pending_tier' => Tier::Tier2,
            'status' => SubscriptionStatus::Pending,
            'seat_quantity' => 5,
            'discount_basis_points' => 500,
        ]);
        OrganizationSeat::create([
            'user_id' => $owner->id,
            'status' => OrganizationSeatStatus::Reserved,
        ]);

        app(ProcessRazorpayWebhook::class)->handle('team_activated', [
            'event' => 'subscription.activated',
            'payload' => ['subscription' => ['entity' => [
                'id' => 'sub_team_lifecycle',
                'quantity' => 5,
                'current_start' => now()->timestamp,
                'current_end' => now()->addMonth()->timestamp,
            ]]],
        ]);
        $this->assertSame(Tier::Tier2, $owner->refresh()->tier);
        $this->assertSame(0, $owner->credits_balance);
        $this->assertSame(OrganizationSeatStatus::Active, OrganizationSeat::query()->firstOrFail()->status);

        $payload = [
            'event' => 'subscription.charged',
            'payload' => ['subscription' => ['entity' => [
                'id' => 'sub_team_lifecycle',
                'quantity' => 5,
                'current_start' => now()->timestamp,
                'current_end' => now()->addMonth()->timestamp,
            ]]],
        ];
        app(ProcessRazorpayWebhook::class)->handle('team_charged', $payload);
        app(ProcessRazorpayWebhook::class)->handle('team_charged', $payload);

        $this->assertSame(200, $owner->refresh()->credits_balance);
        $this->assertDatabaseCount('credit_ledger', 1);
        $this->assertSame(Tier::Tier2, $subscription->refresh()->tier);
    }

    public function test_pending_invitations_reserve_capacity_and_acceptance_activates_a_seat(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->organization('Seats Co');
        Subscription::create([
            'organization_id' => $organization->id,
            'purchaser_user_id' => $owner->id,
            'provider_subscription_id' => 'sub_seats',
            'tier' => Tier::Tier2,
            'status' => SubscriptionStatus::Active,
            'seat_quantity' => 2,
            'discount_basis_points' => 500,
            'current_period_end' => now()->addMonth(),
        ]);
        OrganizationSeat::create(['user_id' => $owner->id, 'status' => OrganizationSeatStatus::Active]);

        $this->actingAs($owner)->post(route('members.invitations.store'), [
            'email' => 'member@example.com',
            'role' => Role::Member->value,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('members.invitations.store'), [
            'email' => 'second@example.com',
            'role' => Role::Member->value,
        ])->assertRedirect()->assertSessionHasErrors('email');

        app(TenantContext::class)->set($organization);
        $invitation = Invitation::query()->where('email', 'member@example.com')->firstOrFail();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->actingAs($member)->get(route('invitations.accept', [
            'organization' => $organization,
            'token' => $invitation->token,
        ]))->assertRedirect(route('dashboard'));

        $this->assertTrue($organization->members()->whereKey($member)->exists());
        $this->assertSame(Tier::Tier2, $member->refresh()->tier);
        $this->assertSame(200, $member->credits_balance);
        $this->assertDatabaseHas('organization_seats', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'status' => OrganizationSeatStatus::Active->value,
        ]);

        $this->actingAs($owner)->delete(route('members.destroy', $member))->assertRedirect();
        $this->assertSame(Tier::Free, $member->refresh()->tier);
        $this->assertFalse($organization->members()->whereKey($member)->exists());
    }

    public function test_member_administration_is_denied_across_tenants(): void
    {
        [$alice, $aliceOrganization] = $this->organization('Alice Co');
        [$bob, $bobOrganization] = $this->organization('Bob Co');
        $bobInvitation = Invitation::create([
            'email' => 'private@bob.test',
            'role' => Role::Member,
            'token' => hash('sha256', 'bob-token'),
            'invited_by' => $bob->id,
            'expires_at' => now()->addDay(),
        ]);

        app(TenantContext::class)->set($aliceOrganization);
        $alice->update(['current_organization_id' => $aliceOrganization->id]);
        $this->actingAs($alice)
            ->delete(route('members.invitations.destroy', $bobInvitation->id))
            ->assertNotFound();

        app(TenantContext::class)->set($bobOrganization);
        $this->assertNotNull(Invitation::find($bobInvitation->id));
    }

    public function test_only_member_managers_can_change_roles_or_remove_members(): void
    {
        [$owner, $organization] = $this->organization('Authorization Co');
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($manager, ['role' => Role::Member->value]);
        $organization->members()->attach($member, ['role' => Role::Member->value]);
        $manager->update(['current_organization_id' => $organization->id]);

        $this->actingAs($manager)->patch(route('members.update', $member), [
            'role' => Role::Viewer->value,
        ])->assertForbidden();
        $this->actingAs($manager)->delete(route('members.destroy', $member))->assertForbidden();

        $this->actingAs($owner)->patch(route('members.update', $member), [
            'role' => Role::Admin->value,
        ])->assertRedirect();
        $this->assertSame(Role::Admin, $member->roleFor($organization));
    }

    /** @return array{User, Organization} */
    private function organization(string $name): array
    {
        $owner = User::factory()->create();
        $organization = app(CreateOrganization::class)->handle($owner, $name);
        app(TenantContext::class)->set($organization);

        return [$owner, $organization];
    }
}
