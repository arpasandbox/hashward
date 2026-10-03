<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VaultItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'ciphertext',
        'iv',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
