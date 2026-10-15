<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) return null;
        // participants (their own dashboard) sign in elsewhere than admins
        return $request->is('my', 'my/*') ? route('my.login') : route('login');
    }
}
