<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Browser security headers on every web page (no clickjacking, no MIME sniffing, private referrers, no sensors). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $h = $response->headers;
        if (!$h->has('X-Frame-Options')) $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        if (!$h->has('Referrer-Policy')) $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=(), usb=(), interest-cohort=()');
        $h->set('X-Permitted-Cross-Domain-Policies', 'none');
        if ($request->isSecure()) $h->set('Strict-Transport-Security', 'max-age=31536000');
        return $response;
    }
}
