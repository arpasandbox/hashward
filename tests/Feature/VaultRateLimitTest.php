<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class VaultRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_route_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_vault_api_is_rate_limited(): void
    {
        RateLimiter::for('vault', fn (Request $request) => Limit::perMinute(2)->by('rate-limit-test'));

        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();
        $session = ['auth.two_factor_passed' => true];

        $this->actingAs($user)->withSession($session)->getJson('/api/vault/items')->assertOk();
        $this->actingAs($user)->withSession($session)->getJson('/api/vault/items')->assertOk();
        $this->actingAs($user)->withSession($session)->getJson('/api/vault/items')->assertStatus(429);
    }
}
