<?php

namespace Tests\Feature;

use App\Exceptions\InvalidUserRoleException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_user_must_be_host(): void
    {
        $this->expectException(InvalidUserRoleException::class);

        User::query()->create([
            'name' => 'Invitee',
            'email' => 'invitee@example.com',
            'password' => 'password-password',
            'kdf_iterations' => 600000,
            'is_host' => false,
        ]);
    }

    public function test_cannot_create_second_host(): void
    {
        User::factory()->create();

        $this->expectException(InvalidUserRoleException::class);

        User::factory()->create();
    }

    public function test_invitee_must_be_linked_to_host(): void
    {
        $host = User::factory()->create();

        $invitee = User::factory()->invitee($host)->create([
            'email' => 'invitee@example.com',
        ]);

        $this->assertFalse($invitee->is_host);
        $this->assertTrue($invitee->isInvitee());
        $this->assertSame($host->id, $invitee->invited_by);
        $this->assertNotNull($invitee->invited_at);
    }

    public function test_create_invitee_helper_sets_host_relationship(): void
    {
        $host = User::factory()->create();

        $invitee = User::createInvitee([
            'name' => 'Family',
            'email' => 'family@example.com',
            'password' => 'password-password',
            'kdf_iterations' => 600000,
        ], $host);

        $this->assertSame($host->id, $invitee->invited_by);
        $this->assertFalse($invitee->is_host);
    }

    public function test_vault_create_user_command_creates_host_only_once(): void
    {
        $this->artisan('vault:create-user', [
            '--name' => 'Host',
            '--email' => 'host@example.com',
            '--password' => 'password-password',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'host@example.com',
            'is_host' => true,
            'invited_by' => null,
        ]);

        $this->artisan('vault:create-user', [
            '--name' => 'Other',
            '--email' => 'other@example.com',
            '--password' => 'password-password',
        ])->assertFailed();
    }
}
