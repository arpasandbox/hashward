<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_to_two_factor_challenge(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = User::factory()->create([
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.challenge'));
    }

    public function test_authenticated_user_visiting_login_is_not_redirect_looped(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_vault_requires_two_factor_session_flag(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();

        $this->actingAs($user)
            ->get('/vault')
            ->assertRedirect(route('two-factor.challenge'));
    }
}
