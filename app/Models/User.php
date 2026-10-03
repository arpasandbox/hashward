<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        ];
    }

    public function vaultItems(): HasMany
    {
        return $this->hasMany(VaultItem::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
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
