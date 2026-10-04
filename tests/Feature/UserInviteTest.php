<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserInviteTest extends TestCase
{
    use RefreshDatabase;

    private function hostWithVaultSession(): User
    {
        return User::factory()->withTwoFactor()->withVaultCrypto()->create();
    }

    private function twoFactorSession(User $user): array
    {
        return [
            'auth.two_factor_passed' => true,
        ];
    }

    public function test_invitee_cannot_access_invite_management(): void
    {
        $host = $this->hostWithVaultSession();
        $invitee = User::factory()->invitee($host)->withTwoFactor()->withVaultCrypto()->create();

        $this->actingAs($invitee)
            ->withSession($this->twoFactorSession($invitee))
            ->get(route('settings.invites'))
            ->assertForbidden();
    }

    public function test_host_can_create_invite(): void
    {
        $host = $this->hostWithVaultSession();

        $response = $this->actingAs($host)
            ->withSession($this->twoFactorSession($host))
            ->post(route('settings.invites.store'), [
                'email' => 'family@example.com',
            ]);

        $response->assertRedirect(route('settings.invites'));
        $response->assertSessionHas('invite_url');

        $this->assertDatabaseHas('user_invites', [
            'email' => 'family@example.com',
            'invited_by' => $host->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $host->id,
            'event' => 'invite_created',
        ]);
    }

    public function test_guest_can_accept_invite_and_create_invitee_account(): void
    {
        $host = User::factory()->create();
        $plainToken = Str::random(64);

        UserInvite::factory()
            ->forHost($host)
            ->withPlainToken($plainToken)
            ->create(['email' => 'invitee@example.com']);

        $response = $this->post(route('invites.store', $plainToken), [
            'name' => 'Invitee User',
            'password' => 'secure-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'email' => 'invitee@example.com',
            'is_host' => false,
            'invited_by' => $host->id,
        ]);

        $invitee = User::query()->where('email', 'invitee@example.com')->first();
        $this->assertNotNull($invitee);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $invitee->id,
            'event' => 'invite_accepted',
        ]);
    }

    public function test_invalid_invite_token_shows_generic_page(): void
    {
        $this->get(route('invites.show', 'not-a-valid-token'))
            ->assertOk()
            ->assertSee('Invite unavailable');

        $this->post(route('invites.store', 'not-a-valid-token'), [
            'name' => 'Someone',
            'password' => 'secure-password',
        ])
            ->assertOk()
            ->assertSee('Invite unavailable');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'invite_failed',
        ]);
    }

    public function test_host_can_view_pending_invite_link(): void
    {
        $host = $this->hostWithVaultSession();
        $plainToken = Str::random(64);

        $invite = UserInvite::factory()
            ->forHost($host)
            ->withPlainToken($plainToken)
            ->create(['email' => 'pending@example.com']);

        $response = $this->actingAs($host)
            ->withSession($this->twoFactorSession($host))
            ->postJson(route('settings.invites.link', $invite));

        $response->assertOk();
        $url = $response->json('url');
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('/invite/', $url);
        $this->assertNotSame($url, route('invites.show', ['token' => $plainToken]));

        preg_match('#/invite/([^/?]+)#', $url, $matches);
        $this->assertNotEmpty($matches[1]);
        $this->assertNotNull(UserInvite::findByPlainToken($matches[1]));
        $this->assertNull(UserInvite::findByPlainToken($plainToken));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $host->id,
            'event' => 'invite_link_viewed',
        ]);
    }

    public function test_host_cannot_view_link_for_revoked_invite(): void
    {
        $host = $this->hostWithVaultSession();
        $invite = UserInvite::factory()->forHost($host)->create([
            'email' => 'revoked@example.com',
            'revoked_at' => now(),
        ]);

        $this->actingAs($host)
            ->withSession($this->twoFactorSession($host))
            ->postJson(route('settings.invites.link', $invite))
            ->assertUnprocessable()
            ->assertJson(['message' => 'This invite is no longer active.']);
    }

    public function test_host_can_revoke_pending_invite(): void
    {
        $host = $this->hostWithVaultSession();
        $invite = UserInvite::factory()->forHost($host)->create([
            'email' => 'pending@example.com',
        ]);

        $this->actingAs($host)
            ->withSession($this->twoFactorSession($host))
            ->delete(route('settings.invites.destroy', $invite))
            ->assertRedirect();

        $invite->refresh();
        $this->assertNotNull($invite->revoked_at);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $host->id,
            'event' => 'invite_revoked',
        ]);
    }

    public function test_host_can_delete_revoked_invite(): void
    {
        $host = $this->hostWithVaultSession();
        $invite = UserInvite::factory()->forHost($host)->create([
            'email' => 'revoked@example.com',
            'revoked_at' => now(),
        ]);

        $this->actingAs($host)
            ->withSession($this->twoFactorSession($host))
            ->delete(route('settings.invites.destroy', $invite))
            ->assertRedirect()
            ->assertSessionHas('status', 'Invite removed.');

        $this->assertDatabaseMissing('user_invites', [
            'id' => $invite->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $host->id,
            'event' => 'invite_deleted',
        ]);
    }

    public function test_host_cannot_delete_pending_invite_twice_as_removal(): void
    {
        $host = $this->hostWithVaultSession();
        $invite = UserInvite::factory()->forHost($host)->create([
            'email' => 'expired@example.com',
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($host)
            ->withSession($this->twoFactorSession($host))
            ->delete(route('settings.invites.destroy', $invite))
            ->assertRedirect()
            ->assertSessionHasErrors('invite');

        $this->assertDatabaseHas('user_invites', [
            'id' => $invite->id,
        ]);
    }

    public function test_registration_route_still_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
