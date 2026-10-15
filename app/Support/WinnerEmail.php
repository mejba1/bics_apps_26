<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email to raffle winners (Raffle setup > Winner email), per event.
 * Mode: off | auto (sent when the host confirms the winner) | manual (Send button per winner).
 * Texts use the same placeholders and **bold** as the confirmation email, plus {prize} and {prize_title}.
 * Design and colours come from Admin > Email Template; the mail server from Admin > Email Server.
 */
class WinnerEmail
{
    public const MODES = [
        'off' => 'Off — no winner emails',
        'auto' => 'Send automatically when the host confirms a winner',
        'manual' => 'Send manually from this page',
    ];

    public const DEFAULTS = [
        'mode' => 'off',
        'subject' => 'Congratulations {first_name}! You won the {prize_title} at {event_short}',
        'heading' => 'You’re a winner!',
        'intro' => "Dear **{name}**,\nCongratulations! Your registration number **{pin}** was drawn in the **{event_short}** raffle.",
        'collect' => "Please come to the **registration desk** with your registration number or ID card to collect your prize.\nIf you have already left the venue, reply to this email and our team will help you.",
        'closing' => 'Thank you for being part of {event_name}.',
        'bcc_organiser' => '1',
    ];

    public const PLACEHOLDERS = [
        '{name}' => 'Title + last name', '{full_name}' => 'Title + first + last name', '{first_name}' => 'First name',
        '{pin}' => 'Registration number', '{prize}' => 'Prize, e.g. iPad Pro 11', '{prize_title}' => 'Prize title, e.g. First Prize',
        '{event_name}' => 'Event name', '{event_short}' => 'Short event name', '{event_dates}' => 'Event dates', '{event_venue}' => 'Event venue',
    ];

    public static function settings(?Event $event = null): array
    {
        $event = $event ?? Event::current();
        $saved = $event ? json_decode((string) DB::table('settings')->where('key', 'raffle.email.' . $event->id)->value('value'), true) : null;
        return array_merge(static::DEFAULTS, is_array($saved) ? array_intersect_key($saved, static::DEFAULTS) : []);
    }

    public static function save(Event $event, array $values): void
    {
        $clean = [];
        foreach (static::DEFAULTS as $k => $default) $clean[$k] = array_key_exists($k, $values) ? trim((string) $values[$k]) : $default;
        $clean['bcc_organiser'] = !empty($values['bcc_organiser']) && $values['bcc_organiser'] !== '0' ? '1' : '0';
        if (!array_key_exists($clean['mode'], static::MODES)) $clean['mode'] = 'off';
        DB::table('settings')->updateOrInsert(['key' => 'raffle.email.' . $event->id], ['value' => json_encode($clean), 'updated_at' => now(), 'created_at' => now()]);
    }

    /** Placeholder values for one winner. */
    public static function vars(object $person, string $prize, string $prizeTitle, ?Event $event = null): array
    {
        $vars = EmailTemplate::vars(['title' => $person->title ?? '', 'first_name' => $person->firstname ?? '', 'last_name' => $person->lastname ?? '',
            'pin' => $person->qr_code ?? '', 'profession' => $person->profession ?? '']);
        unset($vars['{attachments}']);
        // The winner's own event (a past event's winner must not get the current event's name)
        if ($event) {
            $vars['{event_name}'] = (string) $event->event_name;
            $vars['{event_short}'] = (string) ($event->event_short ?: $event->event_name);
            $vars['{event_dates}'] = $event->datesLabel();
            $vars['{event_venue}'] = (string) $event->event_venue;
        }
        return $vars + ['{prize}' => $prize, '{prize_title}' => $prizeTitle, '{attachments}' => ''];
    }

    /** The email as HTML (also used for the admin preview). */
    public static function render(array $settings, array $vars, string $prize, string $prizeTitle): string
    {
        return view('mail.winner', ['t' => $settings, 'vars' => $vars, 'prize' => $prize, 'prizeTitle' => $prizeTitle])->render();
    }

    /** Send to one winner. Returns null on success, or the error message (also stored on the winner). */
    public static function send(int $winnerId, ?array $settings = null): ?string
    {
        $w = DB::table('winners')->where('id', $winnerId)->first();
        if (!$w) return 'Winner not found.';
        $r = $w->register_id ? DB::table('registrations')->where('id', $w->register_id)->first() : null;
        if (!$r && $w->entry_id) {
            // A list number without a registration: name and email come from the imported file
            $e = DB::table('raffle_entries')->where('id', $w->entry_id)->first();
            $r = (object) ['title' => '', 'firstname' => (string) ($e->name ?? ''), 'lastname' => '', 'qr_code' => $w->winners_number,
                'profession' => '', 'email' => $e->email ?? null];
        }
        if (!$r) return static::fail($w->id, 'Winner not found.');
        if (!filter_var($r->email ?? '', FILTER_VALIDATE_EMAIL)) {
            return static::fail($w->id, $w->register_id ? 'The winner has no valid email address.' : 'No email address for this number (add an Email column to your import).');
        }

        $event = $w->event_id ? Event::find($w->event_id) : Event::current();
        $settings = $settings ?? static::settings($event);
        $vars = static::vars($r, (string) $w->prize, (string) $w->prize_type, $event);
        try {
            $html = static::render($settings, $vars, (string) $w->prize, (string) $w->prize_type);
            $subject = EmailTemplate::text($settings['subject'], $vars);
            $bcc = $settings['bcc_organiser'] === '1' ? static::organiser() : null;
            $mail = Mail::to($r->email, trim($r->firstname . ' ' . $r->lastname));
            if ($bcc) $mail->bcc($bcc);
            $mail->send(new \App\Mail\WinnerMail($html, $subject));
        } catch (\Throwable $e) {
            Log::warning('BICS winner email failed', ['winner_id' => $w->id, 'error' => $e->getMessage()]);
            return static::fail($w->id, \Illuminate\Support\Str::limit($e->getMessage(), 240));
        }
        DB::table('winners')->where('id', $w->id)->update(['emailed_at' => now(), 'email_error' => null]);
        return null;
    }

    /** Organiser address for the copy: the BICS address from the site footer (the last one listed). */
    public static function organiser(): ?string
    {
        $footer = \App\Models\Page::globalContent('footer') ?? PageSeed::footer();
        $emails = array_values(array_filter(Cms::lines($footer['emails'] ?? ''), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        return $emails ? end($emails) : null;
    }

    private static function fail(int $winnerId, string $error): string
    {
        DB::table('winners')->where('id', $winnerId)->update(['email_error' => $error]);
        return $error;
    }
}
