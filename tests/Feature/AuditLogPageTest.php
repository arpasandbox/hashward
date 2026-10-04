<?php

namespace Tests\Feature;

use App\Http\Controllers\Settings\AuditLogController;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_page_is_paginated_per_user(): void
    {
        $user = User::factory()->withTwoFactor()->withVaultCrypto()->create();
        $other = User::factory()->invitee($user)->create();

        $total = AuditLogController::PER_PAGE + 5;

        for ($i = 0; $i < $total; $i++) {
            AuditLog::query()->create([
                'user_id' => $user->id,
                'event' => 'login_success',
                'ip' => '127.0.0.1',
                'created_at' => now()->subMinutes($i),
            ]);
        }

        AuditLog::query()->create([
            'user_id' => $other->id,
            'event' => 'login_success',
            'ip' => '10.0.0.1',
            'created_at' => now(),
        ]);

        $session = ['auth.two_factor_passed' => true];

        $this->actingAs($user)
            ->withSession($session)
            ->get(route('settings.audit'))
            ->assertOk()
            ->assertSee('Showing 1–'.AuditLogController::PER_PAGE.' of '.$total)
            ->assertDontSee('10.0.0.1');

        $this->actingAs($user)
            ->withSession($session)
            ->get(route('settings.audit', ['page' => 2]))
            ->assertOk()
            ->assertSee('Showing '.(AuditLogController::PER_PAGE + 1).'–'.$total.' of '.$total)
            ->assertDontSee('10.0.0.1');
    }
}
