<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RotateMasterPasswordRequest;
use App\Http\Requests\Settings\SetupCryptoRequest;
use App\Models\VaultItem;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CryptoSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'kdf_salt' => $user->kdf_salt,
            'kdf_iterations' => $user->kdf_iterations,
            'vault_check' => $user->vault_check,
            'vault_configured' => $user->hasVaultSetup(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function setup(SetupCryptoRequest $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'kdf_salt' => $request->string('kdf_salt'),
            'kdf_iterations' => $request->integer('kdf_iterations'),
            'vault_check' => $request->string('vault_check'),
        ])->save();

        $audit->log($user, 'vault_setup', $request);

        return response()->json(['ok' => true]);
    }

    public function rotate(RotateMasterPasswordRequest $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();

        foreach ($request->input('items') as $row) {
            /** @var VaultItem $item */
            $item = VaultItem::query()->findOrFail($row['id']);
            $this->authorize('update', $item);
        }

        DB::transaction(function () use ($request, $user) {
            $user->forceFill([
                'kdf_salt' => $request->string('kdf_salt'),
                'kdf_iterations' => $request->integer('kdf_iterations'),
                'vault_check' => $request->string('vault_check'),
            ])->save();

            foreach ($request->input('items') as $row) {
                VaultItem::query()->whereKey($row['id'])->update([
                    'ciphertext' => $row['ciphertext'],
                    'iv' => $row['iv'],
                    'version' => $row['version'] ?? 1,
                ]);
            }
        });

        $audit->log($user, 'master_password_changed', $request);

        return response()->json(['ok' => true]);
    }
}
