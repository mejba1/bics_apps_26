<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Email server (SMTP) settings from Admin > System > Email server.
 * Stored in the "settings" table ("mail.*"; the password encrypted with the app key).
 * When switched on they replace the MAIL_* values of .env at runtime; switched off, .env is used as before.
 */
class MailSettings
{
    public const KEYS = ['enabled', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name', 'reply_to'];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (static::$cache !== null) return static::$cache;
        $v = array_fill_keys(static::KEYS, '');
        try {
            foreach (DB::table('settings')->where('key', 'like', 'mail.%')->pluck('value', 'key') as $k => $val) {
                $k = substr($k, 5);
                if (in_array($k, static::KEYS, true)) $v[$k] = (string) $val;
            }
        } catch (\Throwable $e) {
            // settings table not there yet
        }
        return static::$cache = $v;
    }

    public static function enabled(): bool
    {
        $s = static::all();
        return $s['enabled'] === '1' && $s['host'] !== '';
    }

    public static function password(): ?string
    {
        $enc = static::all()['password'];
        if ($enc === '') return null;
        try { return Crypt::decryptString($enc); } catch (\Throwable $e) { return null; }
    }

    /** @param array $values plain values; 'password' => null keeps the stored one */
    public static function save(array $values): void
    {
        if (array_key_exists('password', $values)) {
            if ($values['password'] === null) unset($values['password']);
            else $values['password'] = $values['password'] === '' ? '' : Crypt::encryptString($values['password']);
        }
        foreach ($values as $k => $v) {
            if (!in_array($k, static::KEYS, true)) continue;
            DB::table('settings')->updateOrInsert(['key' => 'mail.' . $k], ['value' => (string) $v, 'updated_at' => now(), 'created_at' => now()]);
        }
        static::$cache = null;
    }

    public static function forget(): void
    {
        static::$cache = null;
    }

    /** Mail config array for a given set of values (stored ones by default). */
    public static function smtpConfig(?array $s = null, ?string $password = null): array
    {
        $s = $s ?? static::all();
        $enc = $s['encryption'] ?? '';
        return [
            'transport' => 'smtp',
            'host' => $s['host'],
            'port' => (int) ($s['port'] ?: 587),
            'encryption' => in_array($enc, ['tls', 'ssl'], true) ? $enc : null,
            'username' => $s['username'] ?: null,
            'password' => $password ?? static::password(),
            'timeout' => 20,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ];
    }

    /**
     * Use the admin settings for outgoing mail. Called on every request (AppServiceProvider).
     * Development / test mailers (log, array) are never switched to SMTP, so local testing never sends real email.
     */
    public static function apply(): void
    {
        if (!static::enabled()) return;
        $s = static::all();
        config(['mail.mailers.smtp' => array_merge(config('mail.mailers.smtp', []), static::smtpConfig($s))]);
        if ($s['from_address']) config(['mail.from.address' => $s['from_address']]);
        if ($s['from_name']) config(['mail.from.name' => $s['from_name']]);
        if ($s['reply_to']) config(['mail.reply_to' => ['address' => $s['reply_to'], 'name' => $s['from_name'] ?: config('mail.from.name')]]);
        if (app()->bound('mail.manager')) app('mail.manager')->purge('smtp');
    }

    /** What is actually used right now (for the admin page). */
    public static function effective(): array
    {
        $smtp = config('mail.mailers.smtp');
        return [
            'source' => static::enabled() ? 'panel' : 'env',
            'mailer' => config('mail.default'),
            'host' => $smtp['host'] ?? '',
            'port' => $smtp['port'] ?? '',
            'encryption' => $smtp['encryption'] ?? '',
            'username' => $smtp['username'] ?? '',
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }
}
