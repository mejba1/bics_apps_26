<?php

namespace App\Http\Middleware;

use App\Support\FormGuard as Guard;
use Closure;
use Illuminate\Http\Request;

/** Route middleware "form.guard:{form}": rejects bot submissions of a public form (see App\Support\FormGuard). */
class FormGuard
{
    public function handle(Request $request, Closure $next, string $form)
    {
        $why = Guard::problem($request, $form);
        if ($why === null) return $next($request);

        Guard::blocked($request, $form, $why);
        $message = $why === 'captcha failed'
            ? 'Please complete the "I am human" check and try again.'
            : ($why === 'form too old' || str_contains($why, 'token')
                ? 'This page has been open too long. Please reload the page and try again.'
                : 'Your submission could not be verified. Please wait a moment and try again.');
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'errors' => ['form' => [$message]], 'guard' => true], 422)
            : back()->withInput($request->except(['password', Guard::TOKEN, 'cf-turnstile-response']))->withErrors(['form' => $message, 'email' => $message])->with('error', $message);
    }
}
