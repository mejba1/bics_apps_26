<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bot / spam protection for the public forms (registration, returning registration, contact, participant sign-in,
 * admin sign-in). Rendered with @include('partials.form-guard', ['form' => 'contact']) and checked by the
 * "form.guard:contact" route middleware:
 *   1. honeypot: a hidden "website" field people never see or fill in
 *   2. time trap: an encrypted, form-bound timestamp — sent back faster than a person could fill the form, or
 *      older than a day, or copied from another form → rejected
 *   3. optional Cloudflare Turnstile (System › Platform settings › Spam protection), off until keys are entered
 * Blocked attempts are logged and counted per day (shown on the Platform settings page).
 */
class FormGuard
{
    public const HONEYPOT = 'fg_website';
    public const TOKEN = '_fg';
    public const MAX_AGE = 86400;

    /** Seconds a person needs at least, per form. */
    public const MIN_SECONDS = ['registration' => 5, 'contact' => 3, 'returning' => 2, 'my-login' => 1, 'admin-login' => 1];

    /** Settings keys (settings table). */
    public const KEYS = ['turnstile_site' => 'security.turnstile_site', 'turnstile_secret' => 'security.turnstile_secret', 'block_disposable' => 'security.block_disposable'];

    private static ?array $settings = null;

    public static function settings(): array
    {
        if (static::$settings !== null) return static::$settings;
        $rows = DB::table('settings')->whereIn('key', array_values(static::KEYS))->pluck('value', 'key');
        return static::$settings = [
            'turnstile_site' => (string) ($rows[static::KEYS['turnstile_site']] ?? ''),
            'turnstile_secret' => (string) ($rows[static::KEYS['turnstile_secret']] ?? ''),
            'block_disposable' => ($rows[static::KEYS['block_disposable']] ?? '1') === '1',
        ];
    }

    public static function save(array $values): void
    {
        $now = now();
        foreach (static::KEYS as $k => $key) {
            if (!array_key_exists($k, $values)) continue;
            $v = is_bool($values[$k]) ? ($values[$k] ? '1' : '0') : trim((string) $values[$k]);
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $v, 'updated_at' => $now, 'created_at' => $now]);
        }
        static::$settings = null;
    }

    public static function turnstileOn(): bool
    {
        $s = static::settings();
        return $s['turnstile_site'] !== '' && $s['turnstile_secret'] !== '';
    }

    public static function token(string $form): string
    {
        return encrypt(['f' => $form, 't' => time()]);
    }

    /** null when the request looks like a person, otherwise the reason it was blocked (for the log). */
    public static function problem(Request $request, string $form): ?string
    {
        foreach ([static::HONEYPOT, 'reg_hp_check', 'website'] as $trap) {
            if (trim((string) $request->input($trap)) !== '') return 'honeypot';
        }
        try {
            $t = decrypt((string) $request->input(static::TOKEN));
        } catch (\Throwable $e) {
            return 'missing or altered form token';
        }
        if (!is_array($t) || ($t['f'] ?? null) !== $form || !is_int($t['t'] ?? null)) return 'token from another form';
        $age = time() - $t['t'];
        if ($age < (static::MIN_SECONDS[$form] ?? 2)) return "sent too fast ({$age}s)";
        if ($age > static::MAX_AGE) return 'form too old';
        // admin sign-in never depends on Cloudflare (wrong keys must not lock the admins out); it has a lockout instead
        if ($form !== 'admin-login' && static::turnstileOn() && !static::turnstileOk($request)) return 'captcha failed';
        return null;
    }

    public static function turnstileOk(Request $request): bool
    {
        $response = (string) $request->input('cf-turnstile-response');
        if ($response === '' || strlen($response) > 2048) return false;
        try {
            $r = Http::asForm()->timeout(6)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => static::settings()['turnstile_secret'], 'response' => $response, 'remoteip' => $request->ip(),
            ]);
            return (bool) $r->json('success');
        } catch (\Throwable $e) {
            // Cloudflare cannot be reached: do not lock people out (honeypot, time trap and rate limits still apply)
            Log::warning('BICS Turnstile check failed, allowing the request', ['error' => $e->getMessage()]);
            return true;
        }
    }

    public static function blocked(Request $request, string $form, string $why): void
    {
        Log::notice('BICS form blocked', ['form' => $form, 'reason' => $why, 'ip' => $request->ip(), 'ua' => substr((string) $request->userAgent(), 0, 160)]);
        $key = 'formguard:' . now()->toDateString();
        Cache::add($key, 0, now()->addDays(8));
        Cache::increment($key);
    }

    /** Blocked attempts in the last $days days. */
    public static function blockedCount(int $days = 7): int
    {
        $n = 0;
        for ($i = 0; $i < $days; $i++) $n += (int) Cache::get('formguard:' . now()->subDays($i)->toDateString(), 0);
        return $n;
    }

    /* ---------- content checks ---------- */

    /** Throw-away mailbox services (only used when "Block disposable email addresses" is on). */
    public const DISPOSABLE = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org', 'sharklasers.com', 'grr.la', '10minutemail.com',
        '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'tempmailo.com', 'tempmail.net', 'tempail.com', 'yopmail.com',
        'yopmail.net', 'yopmail.fr', 'trashmail.com', 'trashmail.de', 'getnada.com', 'nada.email', 'dispostable.com', 'maildrop.cc',
        'mailnesia.com', 'mintemail.com', 'throwawaymail.com', 'fakeinbox.com', 'emailondeck.com', 'mohmal.com', 'moakt.com',
        'spamgourmet.com', 'mailcatch.com', 'mytemp.email', 'tempr.email', 'discard.email', 'burnermail.io', 'mail.tm', 'mail.gw',
        'emailfake.com', 'fakemail.net', 'inboxkitten.com', 'mailpoof.com', 'spambox.us', 'trbvm.com', 'tmpmail.org', 'tmpmail.net',
        'getairmail.com', 'dropmail.me', 'harakirimail.com', 'mvrht.com', 'byom.de', 'mailsac.com', 'tempinbox.com', 'luxusmail.org',
        'cock.li', 'mailexpire.com', 'spam4.me', 'incognitomail.org', 'anonaddy.me', 'mailforspam.com', 'jetable.org', 'tempmailaddress.com',
    ];

    public static function disposable(?string $email): bool
    {
        if (!static::settings()['block_disposable'] || !$email || !str_contains($email, '@')) return false;
        $domain = strtolower(trim(substr(strrchr($email, '@'), 1)));
        foreach (static::DISPOSABLE as $d) {
            if ($domain === $d || str_ends_with($domain, '.' . $d)) return true;
        }
        return false;
    }

    /** Text that has no place in a name / institution field: links, e-mail addresses, HTML. */
    public static function hasLinkOrHtml(?string $v): bool
    {
        if ($v === null || $v === '') return false;
        return (bool) preg_match('~(https?://|www\.|<[a-z/!]|\[url|\.(com|net|org|ru|cn|xyz|top|info|biz|io|shop|online|site)\b)~i', $v);
    }

    /** Validation rule: plain text only (no links, e-mail addresses or HTML), e.g. for names. */
    public static function plain(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (is_string($value) && static::hasLinkOrHtml($value)) $fail('Please do not put links or code in the :attribute.');
        };
    }

    /** Validation rule: not a throw-away mailbox (when switched on in Platform settings). */
    public static function realEmail(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (is_string($value) && static::disposable($value)) $fail('Please use your own email address — temporary / disposable mailboxes are not accepted.');
        };
    }

    public static function linkCount(?string $v): int
    {
        return $v ? preg_match_all('~(https?://|www\.)~i', $v) : 0;
    }
}
