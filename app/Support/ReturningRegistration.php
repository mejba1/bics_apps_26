<?php

namespace App\Support;

use App\Mail\RegistrationCodeMail;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * "Already joined a BICS event?" on /registration: find the person (email, participant ID or an earlier
 * registration number), email a one-time code, then fill the form with what we know so they only check and submit.
 * Codes use participant_login_codes (same rules as the dashboard sign-in: 20 minutes, one use, 5 tries).
 */
class ReturningRegistration
{
    public const SESSION = 'returning_registration';
    public const SESSION_MINUTES = 30;
    /** Filled from earlier records and not changeable on the form (identity). */
    public const LOCKED = ['firstname', 'lastname', 'email', 'gender', 'birthday'];

    /** The participant for "email / MBR-000123 / 123456", or null. Only active profiles with an email can continue. */
    public static function find(string $q): ?Participant
    {
        $q = trim($q);
        $p = null;
        if (filter_var($q, FILTER_VALIDATE_EMAIL)) {
            $email = mb_strtolower($q);
            $p = Participant::where('email', $email)->first();
            if (!$p) {
                $pid = DB::table('registrations')->whereRaw('LOWER(email) = ?', [$email])->whereNotNull('participant_id')->orderByDesc('id')->value('participant_id');
                $p = $pid ? Participant::find($pid) : null;
            }
        } elseif (preg_match('/^[A-Za-z]{2,10}-?\d{1,6}$/', $q)) {
            [$prefix, $num] = preg_split('/-?(?=\d)/', strtoupper($q), 2);
            $p = Participant::where('code', $prefix . '-' . str_pad($num, 6, '0', STR_PAD_LEFT))->first();
        } elseif (preg_match('/^\d{6}$/', $q)) {
            $pid = DB::table('registrations')->where('qr_code', $q)->value('participant_id');
            $p = $pid ? Participant::find($pid) : null;
        }
        // a merged duplicate leads to the profile it was merged into
        if (!$p && preg_match('/^[A-Za-z]{2,10}-\d{6}$/', $q)) {
            $old = Participant::onlyTrashed()->where('code', strtoupper($q))->whereNotNull('merged_into_id')->first();
            $p = $old ? Participant::find($old->merged_into_id) : null;
        }
        return $p && $p->status === 'active' && $p->email ? $p : null;
    }

    /** "ab•••••@gmail.com" — enough to recognise, not to reveal. */
    public static function mask(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $keep = mb_substr($user, 0, min(2, max(1, mb_strlen($user) - 1)));
        return $keep . str_repeat('•', max(3, min(8, mb_strlen($user) - mb_strlen($keep)))) . '@' . $domain;
    }

    /** Email a 6-digit code. False when too many codes were asked for recently or the email failed. */
    public static function sendCode(Participant $p, ?string $ip): bool
    {
        if (DB::table('participant_login_codes')->where('participant_id', $p->id)->where('created_at', '>', now()->subMinutes(15))->count() >= 3) return false;
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('participant_login_codes')->insert([
            'participant_id' => $p->id, 'token_hash' => hash('sha256', Str::random(48)),   // no link for this code
            'code_hash' => hash_hmac('sha256', $p->id . '|' . $code, (string) config('app.key')),
            'expires_at' => now()->addMinutes(ParticipantAccess::LINK_MINUTES), 'ip' => $ip ? Str::limit($ip, 45, '') : null, 'created_at' => now(),
        ]);
        try {
            Mail::to($p->email, $p->name)->send(new RegistrationCodeMail($p, $code, ParticipantAccess::LINK_MINUTES, (string) (EventSettings::get('event_name') ?: 'BICS')));
            return true;
        } catch (\Throwable $e) {
            Log::warning('BICS returning-registration code email failed', ['participant_id' => $p->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /** The current event's registration of this person, if they already registered. */
    public static function alreadyRegistered(Participant $p): ?object
    {
        $event = \App\Models\Event::current();
        $venue = EventSettings::get('venue_code', config('bics.venue'));
        return DB::table('registrations')
            ->where(fn ($q) => $q->where('participant_id', $p->id)->orWhereRaw('LOWER(email) = ?', [$p->email]))
            ->where(fn ($q) => $event ? $q->where('event_id', $event->id)->orWhere('venue', $venue) : $q->where('venue', $venue))
            ->orderByDesc('id')->first(['id', 'qr_code', 'created_at']);
    }

    /** Values for the registration form: latest registration first, the profile for anything missing. */
    public static function prefill(Participant $p): array
    {
        $r = DB::table('registrations')->where('participant_id', $p->id)->orderByDesc('id')->first();
        $pick = fn (...$v) => collect($v)->first(fn ($x) => $x !== null && trim((string) $x) !== '');
        $birthday = $p->birth_date?->format('Y-m-d') ?: BirthDate::parse($r->birthday ?? null);
        // phone is stored as "+880-1712345678"
        $phone = $pick($r->phone ?? null, $p->mobile);
        $dial = null;
        if ($phone && preg_match('/^(\+\d{1,4})-(.+)$/', $phone, $m)) { $dial = $m[1]; $phone = $m[2]; }
        $photo = static::photoPath($p, $r);
        return [
            'title' => $pick($r->title ?? null, $p->title),
            'firstname' => $pick($p->first_name, $r->firstname ?? null),
            'lastname' => $pick($p->last_name, $r->lastname ?? null),
            'gender' => in_array($r->gender ?? null, ['Male', 'Female'], true) ? $r->gender : null,
            'birthday' => $birthday,
            'email' => $p->email,
            'country' => $pick($r->country ?? null, $p->country),
            'countryCode' => $dial,
            'phone' => $phone ? preg_replace('/[^0-9\s\-]/', '', $phone) : null,
            'profession' => $pick($r->profession ?? null, $p->category),
            'specialty' => $pick($r->specialty ?? null, $p->department),
            'designation' => $pick($p->designation, $r->designation ?? null),
            'institute' => $pick($p->organization, $r->institute ?? null),
            'institute_address' => $r->institute_address ?? null,
            'identification_type' => $r->identification_type ?? null,
            'identification_no' => $r->identification_no ?? null,
            'photo_url' => $photo ? Thumb::url($photo, 480) : null,
            'participant_code' => $p->code,
            'name' => $p->name,
            'events' => $p->events()->pluck('event_short')->filter()->values()->all(),
        ];
    }

    /** The photo to reuse ("upload/…"): the profile photo, else the latest registration's photo — null if none. */
    public static function photoPath(Participant $p, ?object $r = null): ?string
    {
        $r = $r ?: DB::table('registrations')->where('participant_id', $p->id)->orderByDesc('id')->first();
        foreach ([$p->photo, ($r && $r->image && $r->image !== 'no-photo.png') ? 'upload/images/' . $r->image : null] as $path) {
            if ($path && preg_match('#^upload/[A-Za-z0-9._\-/]+\.(jpe?g|png|webp)$#i', $path) && is_file(base_path($path))) return $path;
        }
        return null;
    }
}
