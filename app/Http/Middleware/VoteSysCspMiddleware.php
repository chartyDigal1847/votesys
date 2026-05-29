<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VoteSysCspMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response  = $next($request);
        $portalUrl = config('app.portal_url', 'https://deoris.test');
        $debugConnectSrc = app()->hasDebugModeEnabled() ? ' http://127.0.0.1:7481' : '';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' " . $portalUrl . " https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "script-src-elem 'self' 'unsafe-inline' " . $portalUrl . " https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self' " . $portalUrl . $debugConnectSrc,
            "frame-ancestors " . $portalUrl,
            "frame-src 'self'",
            "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
