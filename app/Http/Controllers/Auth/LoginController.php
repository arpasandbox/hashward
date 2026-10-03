<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $key = 'login:'.$request->ip().':'.$request->string('email')->lower();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => __('Too many login attempts. Please try again shortly.')]);
        }

        RateLimiter::hit($key, 60);

        /** @var User|null $user */
        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user?->isLocked()) {
            return back()->withErrors(['email' => __('This account is temporarily locked. Try again later.')]);
        }

        if (! Auth::attempt($request->only('email', 'password'), false)) {
            $user?->registerFailedLogin();
            $audit->log($user, 'login_failed', $request);

            return back()->withErrors(['email' => __('These credentials do not match our records.')]);
        }

        $request->session()->regenerate();
        RateLimiter::clear($key);

        /** @var User $authenticated */
        $authenticated = $request->user();
        $authenticated->clearLoginFailures();
        $request->session()->put('auth.two_factor_passed', false);

        $audit->log($authenticated, 'login_success', $request);

        if (! $authenticated->totp_confirmed_at) {
            return redirect()->route('two-factor.setup');
        }

        return redirect()->route('two-factor.challenge');
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            $audit->log($user, 'logout', $request);
        }

        return redirect()->route('login');
    }
}
