<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WebSocket private-channel authorization follows the same application tenant boundary:
 * a user may only subscribe to their own channel.
 */
class PrivateChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    // NOTE: the broadcaster must be chosen before boot (phpunit.xml sets
    // BROADCAST_CONNECTION=reverb). Channel callbacks are registered on the driver
    // instance resolved at boot, so flipping config() in setUp() would leave the
    // reverb driver with zero channels — denying everything and passing these
    // negative tests for the wrong reason.

    public function test_user_can_authorize_their_own_private_channel(): void
    {
        $alice = User::factory()->create();

        $this->actingAs($alice)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$alice->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_user_cannot_authorize_another_users_private_channel(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$bob->id,
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_authorize_a_private_channel(): void
    {
        $bob = User::factory()->create();

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$bob->id,
        ])->assertStatus(403);
    }
}
