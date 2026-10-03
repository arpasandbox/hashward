<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorRequest;
use App\Services\RecoveryCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorSetupController extends Controller
{
    public function create(Request $request, Google2FA $google2fa): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->totp_confirmed_at) {
            return redirect()->route('two-factor.challenge');
        }

        if (! $user->totp_secret) {
            $user->forceFill([
                'totp_secret' => $google2fa->generateSecretKey(),
            ])->save();
        }

        return view('auth.two-factor-setup', [
            'secret' => $user->totp_secret,
            'email' => $user->email,
        ]);
    }

    public function store(
        TwoFactorRequest $request,
        Google2FA $google2fa,
        RecoveryCodeService $recoveryCodes
    ): RedirectResponse {
        $user = $request->user();

        if ($user->totp_confirmed_at) {
            return redirect()->route('vault');
        }

        $code = str_replace(' ', '', $request->string('code'));

        if (! $google2fa->verifyKey($user->totp_secret, $code)) {
            return back()->withErrors(['code' => __('Invalid authentication code.')]);
        }

        $generated = $recoveryCodes->generate();

        $user->forceFill([
            'totp_confirmed_at' => now(),
            'recovery_codes' => $generated['hashed'],
        ])->save();

        $request->session()->put('auth.two_factor_passed', true);
        $request->session()->flash('recovery_codes_plain', $generated['plain']);

        if (! $user->hasVaultSetup()) {
            return redirect()->route('vault.setup');
        }

        return redirect()->route('vault');
    }
}
