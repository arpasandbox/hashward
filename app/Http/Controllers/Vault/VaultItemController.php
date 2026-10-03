<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\BulkStoreVaultItemsRequest;
use App\Http\Requests\Vault\StoreVaultItemRequest;
use App\Http\Requests\Vault\UpdateVaultItemRequest;
use App\Models\VaultItem;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VaultItemController extends Controller
{
    public function index(AuditLogger $audit, Request $request): JsonResponse
    {
        $this->authorize('viewAny', VaultItem::class);

        $items = VaultItem::query()
            ->orderByDesc('updated_at')
            ->get(['id', 'ciphertext', 'iv', 'version', 'created_at', 'updated_at']);

        $audit->log($request->user(), 'vault_read', $request);

        return response()->json(['data' => $items]);
    }

    public function store(StoreVaultItemRequest $request, AuditLogger $audit): JsonResponse
    {
        $this->authorize('create', VaultItem::class);

        $item = VaultItem::query()->create([
            'user_id' => $request->user()->id,
            'ciphertext' => $request->string('ciphertext'),
            'iv' => $request->string('iv'),
            'version' => $request->integer('version', 1),
        ]);

        $audit->log($request->user(), 'item_created', $request);

        return response()->json(['data' => $item->only(['id', 'ciphertext', 'iv', 'version', 'created_at', 'updated_at'])], 201);
    }

    public function update(UpdateVaultItemRequest $request, VaultItem $vaultItem, AuditLogger $audit): JsonResponse
    {
        $this->authorize('update', $vaultItem);

        $vaultItem->update([
            'ciphertext' => $request->string('ciphertext'),
            'iv' => $request->string('iv'),
            'version' => $request->integer('version', $vaultItem->version),
        ]);

        $audit->log($request->user(), 'item_updated', $request);

        return response()->json(['data' => $vaultItem->only(['id', 'ciphertext', 'iv', 'version', 'created_at', 'updated_at'])]);
    }

    public function destroy(VaultItem $vaultItem, AuditLogger $audit, Request $request): JsonResponse
    {
        $this->authorize('delete', $vaultItem);

        $vaultItem->delete();
        $audit->log($request->user(), 'item_deleted', $request);

        return response()->json(['ok' => true]);
    }

    public function trash(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VaultItem::class);

        $items = VaultItem::query()
            ->onlyTrashed()
            ->orderByDesc('deleted_at')
            ->get(['id', 'ciphertext', 'iv', 'version', 'created_at', 'updated_at', 'deleted_at']);

        return response()->json(['data' => $items]);
    }

    public function restore(VaultItem $vaultItem, AuditLogger $audit, Request $request): JsonResponse
    {
        $this->authorize('restore', $vaultItem);

        $vaultItem->restore();
        $audit->log($request->user(), 'item_restored', $request);

        return response()->json(['ok' => true]);
    }

    public function forceDestroy(VaultItem $vaultItem, AuditLogger $audit, Request $request): JsonResponse
    {
        $this->authorize('forceDelete', $vaultItem);

        $vaultItem->forceDelete();
        $audit->log($request->user(), 'item_deleted', $request);

        return response()->json(['ok' => true]);
    }

    public function bulkStore(BulkStoreVaultItemsRequest $request, AuditLogger $audit): JsonResponse
    {
        $this->authorize('create', VaultItem::class);

        $validated = $request->validated();

        $created = DB::transaction(function () use ($validated, $request) {
            $rows = [];
            foreach ($validated['items'] as $row) {
                $rows[] = VaultItem::query()->create([
                    'user_id' => $request->user()->id,
                    'ciphertext' => $row['ciphertext'],
                    'iv' => $row['iv'],
                    'version' => $row['version'] ?? 1,
                ]);
            }

            return $rows;
        });

        $audit->log($request->user(), 'import', $request);

        return response()->json(['data' => collect($created)->map->only(['id', 'ciphertext', 'iv', 'version'])], 201);
    }
}
