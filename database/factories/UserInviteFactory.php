<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserInvite>
 */
class UserInviteFactory extends Factory
{
    protected $model = UserInvite::class;

    public function definition(): array
    {
        $plainToken = Str::random(64);

        return [
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => UserInvite::hashToken($plainToken),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(UserInvite::EXPIRY_DAYS),
        ];
    }

    public function forHost(User $host): static
    {
        return $this->state(fn () => ['invited_by' => $host->id]);
    }

    public function withPlainToken(string $plainToken): static
    {
        return $this->state(fn () => ['token_hash' => UserInvite::hashToken($plainToken)]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }
}
