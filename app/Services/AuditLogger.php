<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(?User $user, string $event, ?Request $request = null): void
    {
        AuditLog::query()->create([
            'user_id' => $user?->id,
            'event' => $event,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
