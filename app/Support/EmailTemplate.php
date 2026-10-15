<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Registration confirmation email texts (Admin > Email Template).
 * Stored in the "settings" table under "email.*" keys; the defaults below are the original wording.
 *
 * Texts may use placeholders such as {first_name} or {pin} (see PLACEHOLDERS) and **bold**.
 */
class EmailTemplate
{
    public const DEFAULTS = [
        'subject' => 'Registration confirmed – {event_short} · No. {pin}',
        'preheader' => 'Your registration for {event_name} is confirmed. Registration number {pin}.',
        'badge' => 'Registration confirmed',
        'heading' => 'See you at {event_short}!',
        'intro' => 'Dear **{name}**, thank you for registering for the **{event_name}**.',
        'pin_label' => 'Your registration number',
        'pin_note' => 'Keep this number for check-in, verification and the raffle draw.',
        'show_event' => '1',
        'show_profession' => '1',
        'show_button' => '1',
        'button_text' => 'Download your verification card',
        'steps_title' => 'What happens next',
        'steps' => "{attachments}\nBring your **registration number** (or the verification card) to the registration desk.\nYour number also enters you into the **raffle draw** at the summit.",
        'closing' => '',
        'show_banner' => '1',
        'banner' => 'assets/frontend/images/bics-email-banner-2026.png',
        'footer_title' => "Questions? We're happy to help.",
        'footer_note' => 'Organized by Beacon Pharma Ltd.',
        'bottom_note' => 'You received this email because you registered for {event_short}.',
        'brand_color' => '#0e4ca1',
        'accent_color' => '#b22e6b',
    ];

    /** Placeholder => description (shown in the admin editor). */
    public const PLACEHOLDERS = [
        '{name}' => 'Title + last name, e.g. "Dr Rahman"',
        '{full_name}' => 'Title + first + last name',
        '{first_name}' => 'First name',
        '{last_name}' => 'Last name',
        '{pin}' => 'Registration number',
        '{profession}' => 'Profession',
        '{event_name}' => 'Event name',
        '{event_short}' => 'Short event name, e.g. BICS 2026',
        '{event_dates}' => 'Event dates',
        '{event_venue}' => 'Event venue',
        '{attachments}' => 'Sentence naming the attached files (steps only)',
    ];

    public const TOGGLES = ['show_event', 'show_profession', 'show_button', 'show_banner'];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (static::$cache !== null) return static::$cache;
        $values = static::DEFAULTS;
        try {
            $rows = DB::table('settings')->where('key', 'like', 'email.%')->pluck('value', 'key');
            foreach ($rows as $k => $v) {
                $k = substr($k, 6);
                if (array_key_exists($k, $values)) $values[$k] = (string) $v;
            }
        } catch (\Throwable $e) {
            // settings table missing: defaults
        }
        return static::$cache = $values;
    }

    public static function get(string $key): string
    {
        return (string) (static::all()[$key] ?? static::DEFAULTS[$key] ?? '');
    }

    public static function save(array $values): void
    {
        foreach ($values as $k => $v) {
            if (!array_key_exists($k, static::DEFAULTS)) continue;
            DB::table('settings')->updateOrInsert(['key' => 'email.' . $k], ['value' => (string) $v, 'updated_at' => now(), 'created_at' => now()]);
        }
        static::$cache = null;
    }

    public static function reset(): void
    {
        DB::table('settings')->where('key', 'like', 'email.%')->delete();
        static::$cache = null;
    }

    public static function forget(): void
    {
        static::$cache = null;
    }

    /**
     * Placeholder values for one attendee.
     * @param array $person title, first_name, last_name, pin, profession
     */
    public static function vars(array $person, array $attachmentTitles = []): array
    {
        $title = trim($person['title'] ?? '');
        $first = trim($person['first_name'] ?? '');
        $last = trim($person['last_name'] ?? '');
        return [
            '{name}' => trim($title . ' ' . $last) ?: 'Delegate',
            '{full_name}' => trim($title . ' ' . $first . ' ' . $last) ?: 'Delegate',
            '{first_name}' => $first ?: 'Delegate',
            '{last_name}' => $last,
            '{pin}' => (string) ($person['pin'] ?? ''),
            '{profession}' => (string) ($person['profession'] ?? ''),
            '{event_name}' => (string) EventSettings::get('event_name'),
            '{event_short}' => (string) EventSettings::get('event_short', 'BICS'),
            '{event_dates}' => EventSettings::datesLabel(),
            '{event_venue}' => (string) EventSettings::get('event_venue'),
            '{attachments}' => static::attachmentsSentence($attachmentTitles),
        ];
    }

    /** Plain text with placeholders filled in (subject, preheader). */
    public static function text(string $template, array $vars): string
    {
        $vars['{attachments}'] = strip_tags($vars['{attachments}'] ?? '');
        return trim(str_replace('**', '', strtr($template, $vars)));
    }

    /** Safe HTML: escaped text, placeholders filled in, **bold** and line breaks. */
    public static function html(string $template, array $vars, string $boldColor = '#1d2433'): string
    {
        $att = $vars['{attachments}'] ?? '';
        $escaped = [];
        foreach ($vars as $k => $v) $escaped[$k] = $k === '{attachments}' ? '%%ATT%%' : e($v);
        $out = e($template);
        $out = strtr($out, $escaped);
        $out = preg_replace('/\*\*(.+?)\*\*/s', '<strong style="color:' . e($boldColor) . ';">$1</strong>', $out);
        $out = str_replace('%%ATT%%', $att, $out);
        return nl2br($out, false);
    }

    /** Non-empty lines of the steps list, as HTML. Lines that are only {attachments} vanish when nothing is attached. */
    public static function steps(string $steps, array $vars): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $steps);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($line === '{attachments}' && ($vars['{attachments}'] ?? '') === '') continue;
            $out[] = static::html($line, $vars);
        }
        return $out;
    }

    /** "Your Invitation card is attached…", "Attached to this email: A, B and C." (HTML, names escaped) */
    public static function attachmentsSentence(array $titles): string
    {
        $names = array_map(fn ($t) => '<strong>' . e($t) . '</strong>', array_values(array_filter($titles)));
        if (count($names) > 1) return 'Attached to this email: ' . implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names) . '.';
        if (count($names) === 1) return 'Your ' . $names[0] . ' is attached to this email.';
        return '';
    }

    /** Lighter / darker shades of a hex colour for gradients and tints. */
    public static function shade(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) $hex = '0e4ca1';
        $rgb = array_map('hexdec', str_split($hex, 2));
        $rgb = array_map(fn ($c) => (int) round($amount >= 0 ? $c + (255 - $c) * $amount : $c * (1 + $amount)), $rgb);
        return sprintf('#%02x%02x%02x', ...$rgb);
    }
}
