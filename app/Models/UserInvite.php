<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserInvite extends Model
{
    /** @use HasFactory<\Database\Factories\UserInviteFactory> */
    use HasFactory;

    public const EXPIRY_DAYS = 7;

    protected $fillable = [
        'email',
        'token_hash',
        'invited_by',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function findByPlainToken(string $plainToken): ?self
    {
        return static::query()
            ->where('token_hash', static::hashToken($plainToken))
            ->first();
    }

    /**
     * @return array{invite: self, plain_token: string, url: string}
     */
    public static function issue(User $host, string $email): array
    {
        $plainToken = Str::random(64);

        $invite = static::query()->create([
            'email' => $email,
            'token_hash' => static::hashToken($plainToken),
            'invited_by' => $host->id,
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
        ]);

        return [
            'invite' => $invite,
            'plain_token' => $plainToken,
            'url' => route('invites.show', ['token' => $plainToken]),
        ];
    }

    /** Issue a new token; any previous link for this invite stops working. */
    public function rotateToken(): string
    {
        $plainToken = Str::random(64);

        $this->forceFill([
            'token_hash' => static::hashToken($plainToken),
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
        ])->save();

        return $plainToken;
    }

    public function urlForPlainToken(string $plainToken): string
    {
        return route('invites.show', ['token' => $plainToken]);
    }
}
