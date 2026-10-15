<?php

namespace App\Support;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Participation certificates (Admin > Certificates), per event.
 *
 * - Design: one of three templates (classic / modern / elegant), colours, texts with placeholders, logos,
 *   signatures, optional own background artwork. Stored in settings "certificate.design.{event}".
 * - Email: off / auto (sent when the person is checked in) / manual; who is eligible (checked in, or everyone registered).
 *   Stored in settings "certificate.email.{event}".
 * - Each issued certificate gets a number (PREFIX-000123) and a secret token for the public verification page / QR code.
 * - PDFs are made on demand (A4 landscape, DomPDF), never stored.
 */
class Certificate
{
    public const TEMPLATES = [
        'classic' => 'Classic — framed, gold details',
        'modern' => 'Modern — colour band on the left',
        'elegant' => 'Elegant — corner shapes',
    ];

    public const NAME_STYLES = ['serif_italic' => 'Serif italic (formal)', 'serif' => 'Serif', 'sans' => 'Sans-serif (clean)'];

    public const PLACEHOLDERS = [
        '{name}' => 'Title + full name, e.g. Dr Sarah Rahman', '{first_name}' => 'First name', '{last_name}' => 'Last name',
        '{profession}' => 'Profession', '{specialty}' => 'Speciality', '{institute}' => 'Institution', '{country}' => 'Country',
        '{pin}' => 'Registration number', '{certificate_no}' => 'Certificate number',
        '{event_name}' => 'Event name', '{event_short}' => 'Short event name', '{event_dates}' => 'Event dates', '{event_venue}' => 'Event venue',
        '{issue_date}' => 'Date the certificate was issued',
    ];

    public static function designDefaults(?Event $event = null): array
    {
        return [
            'template' => 'classic',
            'primary' => '#0e4ca1',
            'accent' => '#b22e6b',
            'gold' => '#c9a227',
            'paper' => '#ffffff',
            'title' => 'Certificate',
            'subtitle' => 'of Participation',
            'presented' => 'This certificate is proudly presented to',
            'body' => "for actively participating in the **{event_name}**\nheld on {event_dates} at {event_venue}.",
            'footer_note' => '',
            'name_style' => 'serif_italic',
            'include_title' => '1',
            'logo' => 'assets/frontend/images/logo.png',
            'logo2' => '',
            'background' => '',
            'number_prefix' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($event->event_short ?? 'BICS'))) ?: 'BICS',
            'show_number' => '1',
            'show_date' => '1',
            'show_qr' => '1',
            'show_seal' => '1',
            'signatures' => [
                ['name' => '', 'role' => 'Chairperson, Organising Committee', 'image' => ''],
                ['name' => '', 'role' => 'Chair, Scientific Committee', 'image' => ''],
            ],
        ];
    }

    public const EMAIL_DEFAULTS = [
        'mode' => 'manual',          // off | auto | manual
        'eligible' => 'checked_in',  // checked_in | registered
        'subject' => 'Your certificate of participation – {event_short}',
        'body' => "Dear **{name}**,\nThank you for participating in the **{event_name}**.\nYour certificate of participation (No. {certificate_no}) is attached to this email. You can also download it any time with the button below.",
        'attach_pdf' => '1',
    ];

    public const EMAIL_MODES = [
        'off' => 'Off — no certificate emails',
        'auto' => 'Send automatically when the person is checked in',
        'manual' => 'Send manually from the participant list',
    ];

    public const ELIGIBLE = ['checked_in' => 'Only people checked in (attended)', 'registered' => 'Everyone registered'];

    /* ---------------- settings ---------------- */

    public static function design(Event $event): array
    {
        $saved = json_decode((string) DB::table('settings')->where('key', 'certificate.design.' . $event->id)->value('value'), true);
        $d = array_merge(static::designDefaults($event), is_array($saved) ? $saved : []);
        $d['signatures'] = array_values(array_slice(array_map(fn ($s) => [
            'name' => (string) ($s['name'] ?? ''), 'role' => (string) ($s['role'] ?? ''), 'image' => (string) ($s['image'] ?? ''),
        ], is_array($d['signatures']) ? $d['signatures'] : []), 0, 3));
        return $d;
    }

    public static function saveDesign(Event $event, array $values): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'certificate.design.' . $event->id],
            ['value' => json_encode(static::cleanDesign($event, $values)), 'updated_at' => now(), 'created_at' => now()]);
    }

    public static function resetDesign(Event $event): void
    {
        DB::table('settings')->where('key', 'certificate.design.' . $event->id)->delete();
    }

    public static function cleanDesign(Event $event, array $v): array
    {
        $def = static::designDefaults($event);
        $color = fn ($k) => preg_match('/^#[0-9a-f]{6}$/i', (string) ($v[$k] ?? '')) ? strtolower($v[$k]) : $def[$k];
        $text = fn ($k, $max) => mb_substr(trim((string) ($v[$k] ?? $def[$k])), 0, $max);
        $bool = fn ($k) => !empty($v[$k]) && $v[$k] !== '0' && $v[$k] !== 'false' ? '1' : '0';
        $image = fn ($k) => static::safeImagePath($v[$k] ?? '');
        $sigs = [];
        foreach (array_slice(is_array($v['signatures'] ?? null) ? $v['signatures'] : [], 0, 3) as $s) {
            $sigs[] = ['name' => mb_substr(trim((string) ($s['name'] ?? '')), 0, 80), 'role' => mb_substr(trim((string) ($s['role'] ?? '')), 0, 100), 'image' => static::safeImagePath($s['image'] ?? '')];
        }
        return [
            'template' => array_key_exists($v['template'] ?? '', static::TEMPLATES) ? $v['template'] : 'classic',
            'primary' => $color('primary'), 'accent' => $color('accent'), 'gold' => $color('gold'), 'paper' => $color('paper'),
            'title' => $text('title', 60), 'subtitle' => $text('subtitle', 80), 'presented' => $text('presented', 160),
            'body' => $text('body', 600), 'footer_note' => $text('footer_note', 200),
            'name_style' => array_key_exists($v['name_style'] ?? '', static::NAME_STYLES) ? $v['name_style'] : 'serif_italic',
            'include_title' => $bool('include_title'),
            'logo' => $image('logo'), 'logo2' => $image('logo2'), 'background' => $image('background'),
            'number_prefix' => strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($v['number_prefix'] ?? $def['number_prefix']))) ?: $def['number_prefix'],
            'show_number' => $bool('show_number'), 'show_date' => $bool('show_date'), 'show_qr' => $bool('show_qr'), 'show_seal' => $bool('show_seal'),
            'signatures' => $sigs,
        ];
    }

    /** Only images inside the site (assets/ or upload/), never outside paths. */
    public static function safeImagePath(?string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
        if ($path === '' || str_contains($path, '..') || !preg_match('#^(assets|upload)/[A-Za-z0-9_\-/.]+\.(png|jpe?g)$#i', $path)) return '';
        return is_file(base_path($path)) ? $path : '';
    }

    public static function email(Event $event): array
    {
        $saved = json_decode((string) DB::table('settings')->where('key', 'certificate.email.' . $event->id)->value('value'), true);
        return array_merge(static::EMAIL_DEFAULTS, is_array($saved) ? array_intersect_key($saved, static::EMAIL_DEFAULTS) : []);
    }

    public static function saveEmail(Event $event, array $v): void
    {
        $clean = [
            'mode' => array_key_exists($v['mode'] ?? '', static::EMAIL_MODES) ? $v['mode'] : 'manual',
            'eligible' => array_key_exists($v['eligible'] ?? '', static::ELIGIBLE) ? $v['eligible'] : 'checked_in',
            'subject' => mb_substr(trim((string) ($v['subject'] ?? static::EMAIL_DEFAULTS['subject'])), 0, 200),
            'body' => mb_substr(trim((string) ($v['body'] ?? static::EMAIL_DEFAULTS['body'])), 0, 3000),
            'attach_pdf' => !empty($v['attach_pdf']) && $v['attach_pdf'] !== '0' ? '1' : '0',
        ];
        DB::table('settings')->updateOrInsert(['key' => 'certificate.email.' . $event->id], ['value' => json_encode($clean), 'updated_at' => now(), 'created_at' => now()]);
    }

    /* ---------------- people ---------------- */

    /** Registrations of the event (query), optionally only those eligible for a certificate. */
    public static function people(Event $event, bool $onlyEligible = false)
    {
        return DB::table('registrations as r')
            ->where(fn ($q) => $q->where('r.event_id', $event->id)->orWhere('r.venue', $event->venue_code))
            ->where(fn ($q) => $q->whereNull('r.status')->orWhere('r.status', '!=', 'Rejected'))
            ->when($onlyEligible && static::email($event)['eligible'] === 'checked_in', fn ($q) => $q->where('r.verify_status', 'Yes'));
    }

    public static function isEligible(Event $event, object $r): bool
    {
        return static::email($event)['eligible'] !== 'checked_in' || $r->verify_status === 'Yes';
    }

    /** The certificate of a person (issued now if it does not exist yet: number + token). */
    public static function issue(Event $event, object $r): object
    {
        $existing = DB::table('certificates')->where('event_id', $event->id)->where('registration_id', $r->id)->first();
        if ($existing) return $existing;
        return DB::transaction(function () use ($event, $r) {
            $seq = (int) DB::table('certificates')->where('event_id', $event->id)->lockForUpdate()->max('sequence') + 1;
            $prefix = static::design($event)['number_prefix'];
            $number = $prefix . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
            while (DB::table('certificates')->where('number', $number)->exists()) { $seq++; $number = $prefix . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT); }
            $id = DB::table('certificates')->insertGetId([
                'event_id' => $event->id, 'registration_id' => $r->id, 'number' => $number, 'sequence' => $seq,
                'token' => Str::random(40), 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            return DB::table('certificates')->where('id', $id)->first();
        });
    }

    /** Placeholder values for one person (or a sample). */
    public static function vars(Event $event, object $r, ?object $cert = null): array
    {
        $name = trim(preg_replace('/\s+/', ' ', (static::design($event)['include_title'] === '1' && !empty($r->title) ? $r->title . ' ' : '') . ($r->firstname ?? '') . ' ' . ($r->lastname ?? '')));
        $issued = $cert && $cert->issued_at ? \Carbon\Carbon::parse($cert->issued_at) : now();
        return [
            '{name}' => $name, '{first_name}' => (string) ($r->firstname ?? ''), '{last_name}' => (string) ($r->lastname ?? ''),
            '{profession}' => (string) ($r->profession ?? ''), '{specialty}' => (string) ($r->specialty ?? ''), '{institute}' => (string) ($r->institute ?? ''),
            '{country}' => (string) ($r->country ?? ''), '{pin}' => (string) ($r->qr_code ?? ''),
            '{certificate_no}' => $cert->number ?? (static::design($event)['number_prefix'] . '-000001'),
            '{event_name}' => (string) $event->event_name, '{event_short}' => (string) ($event->event_short ?: $event->event_name),
            '{event_dates}' => $event->datesLabel() ?: '—', '{event_venue}' => (string) ($event->event_venue ?: ''),
            '{issue_date}' => $issued->format('j F Y'),
        ];
    }

    public static function sample(): object
    {
        return (object) ['id' => 0, 'title' => 'Dr', 'firstname' => 'Sarah', 'lastname' => 'Rahman', 'profession' => 'Medical Doctor', 'specialty' => 'Oncology',
            'institute' => 'Dhaka Medical College', 'country' => 'Bangladesh', 'qr_code' => '484704', 'verify_status' => 'Yes', 'email' => null];
    }

    /* ---------------- PDF ---------------- */

    /** The certificate PDF (A4 landscape). $design overrides the saved design (live preview). */
    public static function pdf(Event $event, object $r, ?object $cert = null, ?array $design = null)
    {
        $d = $design ?? static::design($event);
        $vars = static::vars($event, $r, $cert);
        $verifyUrl = $cert ? config('bics.public_url') . '/certificate/' . $cert->token : config('bics.public_url') . '/certificate/sample';
        $img = fn ($p) => $p && is_file(base_path($p)) ? 'data:' . mime_content_type(base_path($p)) . ';base64,' . base64_encode(file_get_contents(base_path($p))) : null;
        $data = [
            'd' => $d, 'vars' => $vars, 'event' => $event,
            'logo' => $img($d['logo']), 'logo2' => $img($d['logo2']), 'background' => $img($d['background']),
            'signatures' => array_map(fn ($s) => $s + ['src' => $img($s['image'])], array_filter($d['signatures'], fn ($s) => trim($s['name'] . $s['role'] . $s['image']) !== '')),
            'qr' => $d['show_qr'] === '1' ? 'data:image/svg+xml;base64,' . base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(0)->errorCorrection('M')->generate($verifyUrl)) : null,
            'verifyUrl' => $verifyUrl,
        ];
        return Pdf::loadView('certificates.pdf', $data)->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true);
    }

    public static function fileName(object $r, object $cert): string
    {
        return Str::slug(trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')) ?: 'participant') . '-certificate-' . $cert->number . '.pdf';
    }

    /** "**bold**" and line breaks in a design / email text, HTML-escaped. */
    public static function html(string $text, array $vars, string $boldColor = 'inherit'): string
    {
        $out = e(strtr($text, $vars));
        $out = preg_replace('/\*\*(.+?)\*\*/s', '<b style="color:' . e($boldColor) . '">$1</b>', $out);
        return nl2br($out, false);
    }

    /* ---------------- email ---------------- */

    /**
     * Send the certificate to one person (to their registered address, or to $to when given).
     * Returns null on success, or the error (also stored).
     */
    public static function send(Event $event, int $registrationId, ?string $to = null): ?string
    {
        $r = DB::table('registrations')->where('id', $registrationId)->first();
        if (!$r) return 'Registration not found.';
        $cert = static::issue($event, $r);
        $address = $to ?: $r->email;
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) return static::fail($cert->id, 'No valid email address.');
        $settings = static::email($event);
        try {
            $vars = static::vars($event, $r, $cert);
            $link = config('bics.public_url') . '/certificate/' . $cert->token;
            $html = view('mail.certificate', ['t' => $settings, 'vars' => $vars, 'link' => $link, 'event' => $event])->render();
            $pdf = $settings['attach_pdf'] === '1' ? static::pdf($event, $r, $cert)->output() : null;
            Mail::to($address, trim($r->firstname . ' ' . $r->lastname))
                ->send(new \App\Mail\CertificateMail($html, EmailTemplate::text($settings['subject'], $vars), $pdf, static::fileName($r, $cert)));
        } catch (\Throwable $e) {
            Log::warning('BICS certificate email failed', ['registration_id' => $r->id, 'error' => $e->getMessage()]);
            return static::fail($cert->id, Str::limit($e->getMessage(), 240));
        }
        // an emailed certificate is also given to the participant's dashboard
        DB::table('certificates')->where('id', $cert->id)->update(['emailed_at' => now(), 'email_error' => null, 'updated_at' => now(),
            'assigned_at' => $cert->assigned_at ?? now(), 'assigned_by' => $cert->assigned_by ?? auth()->id()]);
        return null;
    }

    /**
     * Give the certificate to the participant: it appears on their dashboard (My events), under this event.
     * Issues it (number + link) if needed. Returns the certificate.
     */
    public static function assign(Event $event, object $r, ?int $userId): object
    {
        $cert = static::issue($event, $r);
        if (!$cert->assigned_at) {
            DB::table('certificates')->where('id', $cert->id)->update(['assigned_at' => now(), 'assigned_by' => $userId, 'updated_at' => now()]);
        }
        return DB::table('certificates')->where('id', $cert->id)->first();
    }

    /** Take it off the dashboard again (the number and an emailed link stay valid). */
    public static function unassign(Event $event, int $registrationId): bool
    {
        return (bool) DB::table('certificates')->where('event_id', $event->id)->where('registration_id', $registrationId)
            ->update(['assigned_at' => null, 'assigned_by' => null, 'updated_at' => now()]);
    }

    /**
     * Called when someone is checked in (verify page, on-site registration).
     * In "auto" mode the certificate is emailed after the response (the desk never waits for the mail server).
     */
    public static function onCheckedIn(int $registrationId): void
    {
        try {
            $r = DB::table('registrations')->where('id', $registrationId)->first();
            $event = $r ? ($r->event_id ? Event::find($r->event_id) : Event::forVenue($r->venue)) : null;
            if (!$event || static::email($event)['mode'] !== 'auto') return;
            if (DB::table('certificates')->where('event_id', $event->id)->where('registration_id', $r->id)->whereNotNull('emailed_at')->exists()) return;
            app()->terminating(fn () => static::send($event, $registrationId));
        } catch (\Throwable $e) {
            Log::warning('BICS certificate auto-send skipped', ['registration_id' => $registrationId, 'error' => $e->getMessage()]);
        }
    }

    private static function fail(int $certId, string $error): string
    {
        DB::table('certificates')->where('id', $certId)->update(['email_error' => $error, 'updated_at' => now()]);
        return $error;
    }
}
