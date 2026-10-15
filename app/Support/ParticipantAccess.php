<?php

namespace App\Support;

use App\Mail\ParticipantLoginMail;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Participant dashboard (/my): who may sign in and edit, which fields are locked, and the emailed sign-in link / code.
 * Settings (Admin > Participant Profiles > Dashboard settings) live in the settings table:
 *   participants.portal (sign-in on/off) · participants.self_edit (editing on/off) · participants.locked_fields (JSON list).
 * A participant can edit only when both the global switch and their own "may edit" switch are on, and never a locked field.
 */
class ParticipantAccess
{
    public const LINK_MINUTES = 20;
    public const MAX_ATTEMPTS = 5;

    /** Fields a participant can be allowed to edit (email is the sign-in address and links registrations: admins only). */
    public const EDITABLE = [
        'title' => 'Title', 'first_name' => 'First name', 'last_name' => 'Last name', 'designation' => 'Designation', 'organization' => 'Organization',
        'department' => 'Department / speciality', 'category' => 'Category', 'country' => 'Country', 'birth_date' => 'Date of birth', 'photo' => 'Photo', 'logo' => 'Organization logo',
        'mobile' => 'Mobile', 'phone' => 'Telephone', 'whatsapp' => 'WhatsApp', 'address' => 'Office address', 'map_url' => 'Map location',
        'website' => 'Website', 'socials' => 'Social media', 'bio' => 'Biography', 'expertise' => 'Areas of expertise', 'slug' => 'Custom link name',
        'visibility' => 'Public / private choices',
    ];

    /** Locked for everyone unless an admin unlocks them: they are printed on cards and certificates or set by the organizers. */
    public const DEFAULT_LOCKED = ['title', 'first_name', 'last_name', 'category'];

    private static ?array $settings = null;

    public static function settings(): array
    {
        if (static::$settings !== null) return static::$settings;
        $rows = DB::table('settings')->whereIn('key', ['participants.portal', 'participants.self_edit', 'participants.locked_fields'])->pluck('value', 'key');
        $locked = isset($rows['participants.locked_fields']) ? json_decode($rows['participants.locked_fields'], true) : null;
        return static::$settings = [
            'portal' => ($rows['participants.portal'] ?? '1') === '1',
            'edit' => ($rows['participants.self_edit'] ?? '1') === '1',
            'locked' => is_array($locked) ? array_values(array_intersect($locked, array_keys(static::EDITABLE))) : static::DEFAULT_LOCKED,
        ];
    }

    public static function save(bool $portal, bool $edit, array $locked): void
    {
        $now = now();
        foreach (['participants.portal' => $portal ? '1' : '0', 'participants.self_edit' => $edit ? '1' : '0',
            'participants.locked_fields' => json_encode(array_values(array_intersect($locked, array_keys(static::EDITABLE))))] as $k => $v) {
            DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => $now, 'created_at' => $now]);
        }
        static::$settings = null;
    }

    public static function forget(): void
    {
        static::$settings = null;
    }

    public static function canEdit(Participant $p): bool
    {
        return static::settings()['edit'] && $p->editing_enabled && $p->status === 'active';
    }

    public static function isLocked(Participant $p, string $field): bool
    {
        return in_array($field, static::settings()['locked'], true) || in_array($field, $p->locked_fields ?? [], true);
    }

    /** Fields this participant may change right now. */
    public static function editable(Participant $p): array
    {
        if (!static::canEdit($p)) return [];
        return array_values(array_filter(array_keys(static::EDITABLE), fn ($f) => !static::isLocked($p, $f)));
    }

    /* ---------------------------------------------------------------- sign-in */

    /**
     * Email a sign-in link and code. Quietly does nothing for unknown / suspended addresses (the page always says
     * "if this email is registered…", so nobody can find out who is registered). Returns true when an email was sent.
     */
    public static function sendLogin(string $email, ?string $ip): bool
    {
        $email = mb_strtolower(trim($email));
        if (!static::settings()['portal'] || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
        $p = Participant::where('email', $email)->where('status', 'active')->first();
        if (!$p) return false;
        // at most 3 emails per person per 15 minutes
        if (DB::table('participant_login_codes')->where('participant_id', $p->id)->where('created_at', '>', now()->subMinutes(15))->count() >= 3) return false;
        $token = Str::random(48);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('participant_login_codes')->insert([
            'participant_id' => $p->id, 'token_hash' => hash('sha256', $token), 'code_hash' => static::codeHash($p->id, $code),
            'expires_at' => now()->addMinutes(static::LINK_MINUTES), 'ip' => $ip ? Str::limit($ip, 45, '') : null, 'created_at' => now(),
        ]);
        // old, used or expired codes are of no use
        DB::table('participant_login_codes')->where('created_at', '<', now()->subDays(7))->delete();
        try {
            Mail::to($p->email, $p->name)->send(new ParticipantLoginMail($p, route('my.login.link', $token), $code, static::LINK_MINUTES));
            return true;
        } catch (\Throwable $e) {
            Log::warning('BICS participant sign-in email failed', ['participant_id' => $p->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private static function codeHash(int $participantId, string $code): string
    {
        return hash_hmac('sha256', $participantId . '|' . $code, (string) config('app.key'));
    }

    /** The participant for an unused, unexpired link token (the token is used up). */
    public static function useToken(string $token): ?Participant
    {
        return DB::transaction(function () use ($token) {
            $row = DB::table('participant_login_codes')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (!$row || $row->used_at || now()->greaterThan($row->expires_at)) return null;
            DB::table('participant_login_codes')->where('id', $row->id)->update(['used_at' => now()]);
            return Participant::where('id', $row->participant_id)->where('status', 'active')->first();
        });
    }

    /** Sign-in with the 6-digit code: the latest unused code of that email; 5 wrong tries use the code up. */
    public static function useCode(string $email, string $code): ?Participant
    {
        $p = Participant::where('email', mb_strtolower(trim($email)))->where('status', 'active')->first();
        if (!$p) return null;
        return DB::transaction(function () use ($p, $code) {
            $row = DB::table('participant_login_codes')->where('participant_id', $p->id)->whereNull('used_at')->where('expires_at', '>', now())
                ->orderByDesc('id')->lockForUpdate()->first();
            if (!$row || $row->attempts >= static::MAX_ATTEMPTS) return null;
            if (!hash_equals($row->code_hash, static::codeHash($p->id, preg_replace('/\D/', '', $code)))) {
                DB::table('participant_login_codes')->where('id', $row->id)->increment('attempts');
                return null;
            }
            DB::table('participant_login_codes')->where('id', $row->id)->update(['used_at' => now()]);
            return $p;
        });
    }

    /** Email + password sign-in (only for participants who set a password). */
    public static function usePassword(string $email, string $password): ?Participant
    {
        $p = Participant::where('email', mb_strtolower(trim($email)))->where('status', 'active')->whereNotNull('password')->first();
        if (!$p || !\Illuminate\Support\Facades\Hash::check($password, $p->password)) {
            if (!$p) \Illuminate\Support\Facades\Hash::check($password, '$2y$10$aKhVrOy45jI.o8v8wEJWPOl3RKP0FrUO.zL2n42Wd8nk6PFFf.eZO');   // same time either way
            return null;
        }
        return $p;
    }

    /** Remember a sign-in (dashboard Security page). Keeps the last 50. */
    public static function logLogin(Participant $p, string $method, ?string $ip, ?string $agent): void
    {
        DB::table('participant_logins')->insert(['participant_id' => $p->id, 'method' => $method, 'ip' => $ip ? Str::limit($ip, 45, '') : null,
            'user_agent' => $agent ? Str::limit($agent, 250, '') : null, 'created_at' => now()]);
        $keep = DB::table('participant_logins')->where('participant_id', $p->id)->orderByDesc('id')->limit(50)->pluck('id');
        DB::table('participant_logins')->where('participant_id', $p->id)->whereNotIn('id', $keep)->delete();
    }

    /** "Chrome on Windows" from a browser's user agent. */
    public static function device(?string $ua): string
    {
        $ua = (string) $ua;
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox') => 'Firefox', str_contains($ua, 'Chrome') => 'Chrome', str_contains($ua, 'Safari') => 'Safari', default => 'Browser',
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android', (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iPhone / iPad', str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'Linux') => 'Linux', default => 'unknown system',
        };
        return $browser . ' on ' . $os;
    }

    /* ---------------------------------------------------------------- history */

    /** Record what changed ({field: [old, new]}), by the participant or an admin. */
    public static function logChanges(Participant $p, array $before, string $source, ?int $userId, ?string $ip): void
    {
        $changes = [];
        foreach ($before as $k => $old) {
            $new = $p->getAttribute($k);
            if (json_encode($old) !== json_encode($new)) $changes[$k] = [static::short($old), static::short($new)];
        }
        if (!$changes) return;
        DB::table('participant_changes')->insert(['participant_id' => $p->id, 'source' => $source, 'user_id' => $userId, 'changes' => json_encode($changes),
            'ip' => $ip ? Str::limit($ip, 45, '') : null, 'created_at' => now()]);
    }

    private static function short($v)
    {
        if (is_array($v)) return $v;
        return is_string($v) ? Str::limit($v, 300) : $v;
    }
}
