<?php

use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'two_factor' => EnsureTwoFactorVerified::class,
        ]);
        // Avoid /login → / → /login loop when a session already exists (guest middleware default is "/").
        $middleware->redirectUsersTo(fn () => route('two-factor.challenge'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
