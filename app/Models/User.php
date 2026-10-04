<?php

namespace App\Models;

use App\Exceptions\InvalidUserRoleException;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'totp_secret',
        'totp_confirmed_at',
        'recovery_codes',
        'kdf_salt',
        'kdf_iterations',
        'vault_check',
        'failed_logins',
        'locked_until',
        'is_host',
        'invited_by',
        'invited_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
        'recovery_codes',
        'vault_check',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'totp_secret' => 'encrypted',
            'totp_confirmed_at' => 'datetime',
            'recovery_codes' => 'array',
            'locked_until' => 'datetime',
            'kdf_iterations' => 'integer',
            'failed_logins' => 'integer',
            'is_host' => 'boolean',
            'invited_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->assertValidHostInviteeRules();
        });
    }

    public function vaultItems(): HasMany
    {
        return $this->hasMany(VaultItem::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by');
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(self::class, 'invited_by');
    }

    public function sentInvites(): HasMany
    {
        return $this->hasMany(UserInvite::class, 'invited_by');
    }

    public function isHost(): bool
    {
        return (bool) $this->is_host;
    }

    public function isInvitee(): bool
    {
        return ! $this->isHost();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createInvitee(array $attributes, User $host): self
    {
        if (! $host->isHost()) {
            throw new InvalidUserRoleException('Only the host can create invitee accounts.');
        }

        return static::query()->create(array_merge($attributes, [
            'is_host' => false,
            'invited_by' => $host->id,
            'invited_at' => now(),
        ]));
    }

    public static function hostExists(): bool
    {
        return static::query()->where('is_host', true)->exists();
    }

    public function assertValidHostInviteeRules(): void
    {
        if ($this->isHost()) {
            if (static::query()->where('is_host', true)->exists()) {
                throw new InvalidUserRoleException('A host user already exists.');
            }

            if ($this->invited_by !== null) {
                throw new InvalidUserRoleException('The host cannot have an inviter.');
            }

            return;
        }

        if ($this->invited_by === null) {
            if (! static::query()->exists()) {
                throw new InvalidUserRoleException('The first account must be the host.');
            }

            throw new InvalidUserRoleException('Invitees must reference the host via invited_by.');
        }

        $inviter = static::query()->find($this->invited_by);

        if ($inviter === null || ! $inviter->isHost()) {
            throw new InvalidUserRoleException('Invitees must be invited by the host.');
        }
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function hasVaultSetup(): bool
    {
        return $this->kdf_salt !== null && $this->vault_check !== null;
    }

    public function registerFailedLogin(): void
    {
        $this->failed_logins = ($this->failed_logins ?? 0) + 1;

        if ($this->failed_logins >= 5) {
            $minutes = min(60, 15 * (int) floor($this->failed_logins / 5));
            $this->locked_until = now()->addMinutes($minutes);
        }

        $this->save();
    }

    public function clearLoginFailures(): void
    {
        $this->forceFill([
            'failed_logins' => 0,
            'locked_until' => null,
        ])->save();
    }
}
