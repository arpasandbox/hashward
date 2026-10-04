<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_route_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_vault_api_requires_authentication(): void
    {
        $this->getJson('/api/vault/items')->assertUnauthorized();
    }

    public function test_vault_api_requires_two_factor_session_flag(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();

        $this->actingAs($user)
            ->getJson('/api/vault/items')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Two-factor verification required.']);
    }

    public function test_user_cannot_access_another_users_vault_item(): void
    {
        $owner = User::factory()->withTwoFactor()->withVaultCrypto()->create();
        $other = User::factory()->invitee($owner)->withTwoFactor()->withVaultCrypto()->create();

        $item = VaultItem::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'ciphertext' => base64_encode('secret'),
            'iv' => base64_encode(random_bytes(12)),
        ]);

        $this->actingAs($other)
            ->withSession(['auth.two_factor_passed' => true])
            ->putJson("/api/vault/items/{$item->id}", [
                'ciphertext' => base64_encode('tampered'),
                'iv' => base64_encode(random_bytes(12)),
            ])
            ->assertNotFound();
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_ciphertext_in_database_is_not_plaintext_credentials(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();

        VaultItem::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'ciphertext' => base64_encode('Zm9vLWVuY3J5cHRlZA=='),
            'iv' => base64_encode(random_bytes(12)),
        ]);

        $this->assertDatabaseMissing('vault_items', [
            'ciphertext' => 'password123',
        ]);

        $this->assertDatabaseMissing('vault_items', [
            'ciphertext' => '{"password":"secret"}',
        ]);
    }
}
