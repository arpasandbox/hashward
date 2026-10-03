<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        if (! $user->totp_confirmed_at) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Two-factor setup required.'], 401);
            }

            return redirect()->route('two-factor.setup');
        }

        if (! $request->session()->get('auth.two_factor_passed')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Two-factor verification required.'], 401);
            }

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
