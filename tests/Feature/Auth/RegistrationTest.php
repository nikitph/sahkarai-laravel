<?php

namespace Tests\Feature\Auth;

use App\Contracts\Billing\BillingGateway;
use App\Enums\OrganizationSeatStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Mockery;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/register')
                ->where('organizationBilling.enabled', true)
                ->where('organizationBilling.autoApprove', true)
                ->where('organizationBilling.minSeats', 2)
                ->where('organizationBilling.maxSeats', 25)
                ->where('organizationBilling.plans.tier_2.monthly_price', 149900)
                ->where('organizationBilling.discounts.0.percent', 5));
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_users_are_marked_verified_when_auto_verification_is_enabled(): void
    {
        config(['sahkarai.auth.auto_verify_email' => true]);

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'verified@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotNull(auth()->user()?->email_verified_at);
    }

    public function test_new_user_can_create_and_activate_an_organization_plan_during_registration(): void
    {
        config([
            'sahkarai.razorpay.organization_billing.enabled' => true,
            'sahkarai.razorpay.organization_billing.razorpay_enabled' => false,
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Asha Founder',
            'email' => 'founder@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'organization',
            'organization_name' => 'Asha Cooperative',
            'organization_tier' => Tier::Tier2->value,
            'organization_seats' => 6,
        ]);

        $user = User::query()->where('email', 'founder@example.com')->firstOrFail();
        $organization = Organization::query()->where('owner_id', $user->id)->firstOrFail();
        $subscription = Subscription::query()->where('organization_id', $organization->id)->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('members.index', absolute: false));
        $this->assertSame($organization->id, $user->current_organization_id);
        $this->assertSame(Tier::Tier2, $user->refresh()->tier);
        $this->assertSame(200, $user->credits_balance);
        $this->assertSame('local', $subscription->provider);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(6, $subscription->seat_quantity);
        $this->assertSame(1000, $subscription->discount_basis_points);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('organization_seats', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => OrganizationSeatStatus::Active->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->id,
            'actor_id' => $user->id,
            'event' => 'organization.subscription.created',
        ]);
    }

    public function test_organization_registration_requires_valid_plan_details_before_creating_the_user(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Invalid Founder',
            'email' => 'invalid-founder@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'organization',
            'organization_name' => '',
            'organization_tier' => Tier::Free->value,
            'organization_seats' => 26,
        ])->assertSessionHasErrors([
            'organization_name',
            'organization_tier',
            'organization_seats',
        ]);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'invalid-founder@example.com']);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_organization_registration_is_rejected_when_the_feature_is_disabled(): void
    {
        config(['sahkarai.razorpay.organization_billing.enabled' => false]);

        $this->post(route('register.store'), [
            'name' => 'Disabled Founder',
            'email' => 'disabled-founder@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'organization',
            'organization_name' => 'Disabled Cooperative',
            'organization_tier' => Tier::Tier2->value,
            'organization_seats' => 5,
        ])->assertSessionHasErrors('account_type');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'disabled-founder@example.com']);
    }

    public function test_provider_backed_organization_registration_redirects_to_checkout(): void
    {
        config([
            'sahkarai.razorpay.organization_billing.enabled' => true,
            'sahkarai.razorpay.organization_billing.razorpay_enabled' => true,
            'sahkarai.razorpay.organization_billing.discounts.0.offer_id' => 'offer_5',
        ]);
        $gateway = Mockery::mock(BillingGateway::class);
        $gateway->shouldReceive('createOrganizationSubscription')
            ->once()
            ->withArgs(fn (Organization $organization, User $purchaser, Tier $tier, int $seats, string $offerId): bool => $organization->name === 'Provider Cooperative'
                && $purchaser->email === 'provider-founder@example.com'
                && $tier === Tier::Tier3
                && $seats === 5
                && $offerId === 'offer_5')
            ->andReturn(['id' => 'sub_registration_provider']);
        $this->app->instance(BillingGateway::class, $gateway);

        $response = $this->post(route('register.store'), [
            'name' => 'Provider Founder',
            'email' => 'provider-founder@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'organization',
            'organization_name' => 'Provider Cooperative',
            'organization_tier' => Tier::Tier3->value,
            'organization_seats' => 5,
        ]);

        $response->assertRedirect(route('billing.team.index', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('subscriptions', [
            'provider_subscription_id' => 'sub_registration_provider',
            'tier' => Tier::Free->value,
            'pending_tier' => Tier::Tier3->value,
            'status' => SubscriptionStatus::Pending->value,
            'seat_quantity' => 5,
        ]);
    }
}
