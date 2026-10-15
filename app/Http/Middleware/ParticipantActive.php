<?php

namespace App\Http\Middleware;

use App\Support\ParticipantAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Signs a participant out when their profile was suspended or the participant dashboard was switched off. */
class ParticipantActive
{
    public function handle(Request $request, Closure $next)
    {
        $p = Auth::guard('participant')->user();
        // "sign out other devices" and a password change raise session_version: older sessions end here
        $stale = $p && (int) $request->session()->get('participant_sv', 0) !== (int) $p->session_version;
        if ($p && ($stale || $p->status !== 'active' || $p->trashed() || !ParticipantAccess::settings()['portal'])) {
            Auth::guard('participant')->logout();
            $request->session()->regenerate();
            return $request->expectsJson()
                ? response()->json(['message' => 'Your session has ended.'], 401)
                : redirect()->route('my.login')->with('notice', $stale ? 'You were signed out. Please sign in again.' : 'Please contact the organizers — your profile is not available for sign-in.');
        }
        return $next($request);
    }
}
