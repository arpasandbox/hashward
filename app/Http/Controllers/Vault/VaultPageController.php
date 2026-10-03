<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VaultPageController extends Controller
{
    public function setup(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        if ($request->user()->hasVaultSetup()) {
            return redirect()->route('vault');
        }

        return view('vault.setup', [
            'kdfIterations' => $request->user()->kdf_iterations ?: 600000,
        ]);
    }

    public function index(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVaultSetup()) {
            return redirect()->route('vault.setup');
        }

        return view('vault.index', [
            'kdfSalt' => $user->kdf_salt,
            'kdfIterations' => $user->kdf_iterations,
            'vaultCheck' => $user->vault_check,
            'userName' => $user->name,
        ]);
    }
}
