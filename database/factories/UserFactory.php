<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'kdf_iterations' => 600000,
            'is_host' => true,
            'invited_by' => null,
        ];
    }

    public function invitee(User $host): static
    {
        return $this->state(fn () => [
            'is_host' => false,
            'invited_by' => $host->id,
            'invited_at' => now(),
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(function () {
            $secret = (new Google2FA)->generateSecretKey();

            return [
                'totp_secret' => $secret,
                'totp_confirmed_at' => now(),
                'recovery_codes' => [],
            ];
        });
    }

    public function withVaultCrypto(): static
    {
        return $this->state(fn () => [
            'kdf_salt' => base64_encode(random_bytes(16)),
            'vault_check' => base64_encode('opaque-blob'),
        ]);
    }
}
