<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorRequest;
use App\Services\AuditLogger;
use App\Services\RecoveryCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()?->totp_confirmed_at) {
            return redirect()->route('two-factor.setup');
        }

        if ($request->session()->get('auth.two_factor_passed')) {
            return redirect()->route('vault');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(
        TwoFactorRequest $request,
        Google2FA $google2fa,
        RecoveryCodeService $recoveryCodes,
        AuditLogger $audit
    ): RedirectResponse {
        $user = $request->user();
        $code = str_replace(' ', '', $request->string('code'));

        $validTotp = $google2fa->verifyKey($user->totp_secret, $code);

        if (! $validTotp && $user->recovery_codes) {
            $result = $recoveryCodes->consume($code, $user->recovery_codes);
            if ($result['valid']) {
                $user->forceFill(['recovery_codes' => $result['remaining']])->save();
                $validTotp = true;
            }
        }

        if (! $validTotp) {
            $audit->log($user, '2fa_failed', $request);

            return back()->withErrors(['code' => __('Invalid authentication code.')]);
        }

        $request->session()->put('auth.two_factor_passed', true);

        if (! $user->hasVaultSetup()) {
            return redirect()->route('vault.setup');
        }

        return redirect()->route('vault');
    }
}
