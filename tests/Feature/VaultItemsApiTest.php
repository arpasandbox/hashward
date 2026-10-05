<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultItemsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_vault_items(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.two_factor_passed' => true])
            ->getJson('/api/vault/items');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_authenticated_user_can_update_vault_item_via_form_method_spoof(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();
        $item = \App\Models\VaultItem::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'ciphertext' => base64_encode('old'),
            'iv' => base64_encode(random_bytes(12)),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.two_factor_passed' => true])
            ->post("/api/vault/items/{$item->id}", [
                '_token' => csrf_token(),
                '_method' => 'PUT',
                'ciphertext' => base64_encode('encrypted-updated'),
                'iv' => base64_encode(random_bytes(12)),
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('vault_items', [
            'id' => $item->id,
            'ciphertext' => base64_encode('encrypted-updated'),
        ]);
    }

    public function test_authenticated_user_can_create_vault_item(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.two_factor_passed' => true])
            ->postJson('/api/vault/items', [
                'ciphertext' => base64_encode('encrypted-blob'),
                'iv' => base64_encode(random_bytes(12)),
            ]);

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['id', 'ciphertext', 'iv']]);
        $this->assertDatabaseCount('vault_items', 1);
    }

    public function test_browser_style_post_with_csrf_header_succeeds(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.two_factor_passed' => true])
            ->post('/api/vault/items', [
                'ciphertext' => base64_encode('encrypted-blob-browser'),
                'iv' => base64_encode(random_bytes(12)),
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-CSRF-TOKEN' => csrf_token(),
            ]);

        $response->assertCreated();
    }

    public function test_authenticated_user_can_delete_vault_item_via_json_method_spoof(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();
        $item = \App\Models\VaultItem::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'ciphertext' => base64_encode('blob'),
            'iv' => base64_encode(random_bytes(12)),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.two_factor_passed' => true])
            ->postJson("/api/vault/items/{$item->id}", [
                '_method' => 'DELETE',
            ]);

        $response->assertOk();
        $this->assertSoftDeleted('vault_items', ['id' => $item->id]);
    }

    public function test_crypto_endpoint_returns_configuration(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.two_factor_passed' => true])
            ->getJson('/api/crypto');

        $response->assertOk();
        $response->assertJsonPath('vault_configured', true);
        $response->assertJsonStructure(['kdf_salt', 'kdf_iterations', 'vault_check']);
    }
}
