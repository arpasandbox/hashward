<?php

use App\Http\Controllers\Auth\AcceptInviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\UserInviteController;
use App\Http\Controllers\Settings\CryptoSettingsController;
use App\Http\Controllers\Vault\VaultItemController;
use App\Http\Controllers\Vault\VaultPageController;
use App\Models\VaultItem;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('two-factor.challenge');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('/invite/{token}', [AcceptInviteController::class, 'show'])
        ->name('invites.show')
        ->middleware('throttle:invite');
    Route::post('/invite/{token}', [AcceptInviteController::class, 'store'])
        ->name('invites.store')
        ->middleware('throttle:invite');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/two-factor/setup', [TwoFactorSetupController::class, 'create'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [TwoFactorSetupController::class, 'store'])->name('two-factor.setup.store');

    Route::get('/two-factor/challenge', [TwoFactorController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorController::class, 'store'])->middleware('throttle:login');
});

Route::middleware(['auth', 'two_factor'])->group(function () {
    Route::get('/vault/setup', [VaultPageController::class, 'setup'])->name('vault.setup');
    Route::get('/vault', [VaultPageController::class, 'index'])->name('vault');
    Route::get('/settings/audit', [AuditLogController::class, 'index'])->name('settings.audit');

    Route::middleware('host')->group(function () {
        Route::get('/settings/invites', [UserInviteController::class, 'index'])->name('settings.invites');
        Route::post('/settings/invites', [UserInviteController::class, 'store'])->name('settings.invites.store');
        Route::delete('/settings/invites/{userInvite}', [UserInviteController::class, 'destroy'])->name('settings.invites.destroy');
        Route::post('/settings/invites/{userInvite}/link', [UserInviteController::class, 'link'])
            ->middleware('throttle:vault')
            ->name('settings.invites.link');
    });

    Route::middleware('throttle:vault')->prefix('api')->group(function () {
        Route::get('/crypto', [CryptoSettingsController::class, 'show']);
        Route::post('/crypto/setup', [CryptoSettingsController::class, 'setup']);
        Route::put('/crypto/rotate', [CryptoSettingsController::class, 'rotate']);

        Route::get('/vault/items', [VaultItemController::class, 'index']);
        Route::post('/vault/items', [VaultItemController::class, 'store']);
        Route::post('/vault/items/bulk', [VaultItemController::class, 'bulkStore']);
        Route::put('/vault/items/{vaultItem}', [VaultItemController::class, 'update']);
        Route::delete('/vault/items/{vaultItem}', [VaultItemController::class, 'destroy']);
        Route::get('/vault/trash', [VaultItemController::class, 'trash']);
        Route::post('/vault/items/{vaultItem}/restore', [VaultItemController::class, 'restore']);
        Route::delete('/vault/items/{vaultItem}/force', [VaultItemController::class, 'forceDestroy']);

        Route::post('/audit/export', [AuditLogController::class, 'export']);
    });
});

Route::bind('vaultItem', fn (string $value) => VaultItem::withTrashed()->whereKey($value)->firstOrFail());

foreach (['register', 'password/reset', 'forgot-password'] as $blocked) {
    Route::match(['get', 'post'], $blocked, fn () => abort(404));
}
