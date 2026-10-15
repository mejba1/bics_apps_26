<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;

/** Records admin changes (anything but plain page views) in the audit log after they ran. */
class AuditTrail
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        try {
            $status = $response->getStatusCode();
            // successful changes, and refused ones (403) which matter for security; not form mistakes (422) or expired pages (419)
            if (($status < 400 || $status === 403) && Audit::shouldLog($request)) Audit::fromRequest($request, $status);
        } catch (\Throwable $e) {
            // never break the response
        }
        return $response;
    }
}
