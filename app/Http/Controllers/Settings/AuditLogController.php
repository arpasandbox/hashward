<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AuditExportRequest;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public const PER_PAGE = 10;

    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('settings.audit', compact('logs'));
    }

    public function export(AuditExportRequest $request, AuditLogger $audit): JsonResponse
    {
        $audit->log($request->user(), 'export', $request);

        return response()->json(['ok' => true]);
    }
}
