<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    /**
     * @return list<string>
     */
    private function viteDevOrigins(): array
    {
        if (! app()->environment('local')) {
            return [];
        }

        $hotFile = public_path('hot');
        if (! File::exists($hotFile)) {
            return [];
        }

        $origin = rtrim(File::get($hotFile));
        if ($origin === '') {
            return [];
        }

        $origins = [$origin];

        if (str_contains($origin, '127.0.0.1')) {
            $origins[] = str_replace('127.0.0.1', 'localhost', $origin);
        }

        if (str_contains($origin, 'localhost')) {
            $origins[] = str_replace('localhost', '127.0.0.1', $origin);
        }

        return array_values(array_unique($origins));
    }

    private function contentSecurityPolicy(): string
    {
        $scriptSrc = "'self'";
        $styleSrc = "'self'";
        $connectSrc = "'self'";
        $workerSrc = "'self' blob:";

        foreach ($this->viteDevOrigins() as $devOrigin) {
            $wsOrigin = preg_replace('#^http#', 'ws', $devOrigin);
            $scriptSrc .= ' '.$devOrigin;
            $styleSrc .= ' '.$devOrigin;
            $workerSrc .= ' '.$devOrigin;
            $connectSrc .= ' '.$devOrigin.' '.$wsOrigin;
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "worker-src {$workerSrc}",
            "style-src {$styleSrc}",
            "img-src 'self' data:",
            "connect-src {$connectSrc}",
            "object-src 'none'",
            "base-uri 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
        ]);
    }
}
