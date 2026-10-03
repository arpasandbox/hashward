<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('totp_secret')->nullable()->after('password');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret');
            $table->json('recovery_codes')->nullable()->after('totp_confirmed_at');
            $table->string('kdf_salt', 64)->nullable()->after('recovery_codes');
            $table->unsignedInteger('kdf_iterations')->default(600000)->after('kdf_salt');
            $table->text('vault_check')->nullable()->after('kdf_iterations');
            $table->unsignedSmallInteger('failed_logins')->default(0)->after('vault_check');
            $table->timestamp('locked_until')->nullable()->after('failed_logins');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'totp_secret',
                'totp_confirmed_at',
                'recovery_codes',
                'kdf_salt',
                'kdf_iterations',
                'vault_check',
                'failed_logins',
                'locked_until',
            ]);
        });
    }
};
