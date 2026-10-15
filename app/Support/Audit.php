<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Audit log of admin actions (System › Audit log). Admin changes are recorded by App\Http\Middleware\AuditTrail,
 * sign-ins by the auth event listeners (AppServiceProvider). Secrets (passwords, tokens…) are never stored.
 */
class Audit
{
    /** Areas, by the first part of the route name or address. */
    public const AREAS = [
        'participants' => 'Participants', 'nfc' => 'NFC cards', 'card-studio' => 'Card design', 'card-print' => 'Card printing', 'certificates' => 'Certificates', 'idcards' => 'ID cards',
        'raffle' => 'Raffle', 'prize' => 'Raffle', 'registrations' => 'Registrations', 'attendees' => 'Check-in & gifts', 'events' => 'Events',
        'email-template' => 'Email template', 'email-attachments' => 'Email template', 'form-options' => 'Form options', 'cms' => 'Website', 'menu' => 'Website',
        'gallery' => 'Website', 'users' => 'Users', 'roles' => 'Roles', 'account' => 'My account', 'system' => 'System', 'backups' => 'System', 'auth' => 'Sign-in', 'other' => 'Other',
    ];

    /** Readable actions for routes whose name alone says little. */
    private const ACTIONS = [
        'participants.bulk' => 'Bulk change of participants', 'participants.link-settings' => 'Changed link settings', 'participants.dashboard-settings' => 'Changed participant dashboard settings',
        'participants.send-login' => 'Emailed a sign-in link', 'participants.organizations' => 'Renamed / merged organization or category', 'nfc.stock' => 'Added NFC cards to stock', 'nfc.assign-event' => 'Gave NFC cards to an event',
        'nfc.bulk' => 'Bulk change of NFC cards', 'nfc.for' => 'Gave an NFC card', 'card-print.store' => 'Made card print files', 'card-print.printed' => 'Marked a print job printed / not printed',
        'certificates.design' => 'Saved certificate design', 'certificates.design.reset' => 'Reset certificate design', 'certificates.email' => 'Saved certificate email settings',
        'certificates.email.test' => 'Sent a test certificate email', 'certificates.send' => 'Sent certificates', 'certificates.send-one' => 'Sent a certificate', 'certificates.assign' => 'Assigned / removed certificates on dashboards', 'idcards.assign' => 'Assigned / removed ID cards on dashboards',
        'raffle.draw' => 'Drew a raffle winner', 'raffle.redraw' => 'Redrew a raffle winner', 'raffle.accept' => 'Accepted a raffle winner', 'raffle.hosts' => 'Changed raffle hosts',
        'raffle.eligibility' => 'Changed who can win', 'raffle.entries.clear' => 'Cleared the raffle number list', 'raffle.entries.import' => 'Imported raffle numbers', 'raffle.entries.add' => 'Added raffle numbers',
        'raffle.prizes.undo' => 'Undid a raffle win', 'raffle.reset-skipped' => 'Reset skipped raffle prizes', 'prize.winner.email' => 'Emailed a prize winner', 'prize.winner.email-pending' => 'Emailed pending prize winners',
        'prize.winner.email-mode' => 'Changed winner email mode', 'registrations.send-pending' => 'Sent pending confirmation emails', 'registrations.send-email' => 'Resent a confirmation email',
        'events.current' => 'Made an event current', 'events.status' => 'Changed event status', 'events.pin' => 'Pinned an event', 'system.mail.update' => 'Changed email server settings', 'system.mail.test' => 'Sent a test email',
        'system.settings' => 'Changed platform settings', 'system.settings.prune' => 'Removed old log entries', 'backups.create' => 'Made a database backup', 'backups.import' => 'Imported a database', 'backups.restore' => 'Restored a database backup', 'backups.destroy' => 'Deleted a backup',
        'events.unlock' => 'Unlocked an ended event for corrections', 'events.lock' => 'Locked an event again',
        'participants.social-icons.save' => 'Saved a social icon', 'participants.social-icons.destroy' => 'Removed a social icon',
        'attendees.bulk' => 'Checked in / handed gifts to several attendees',
        'documents.save' => 'Uploaded / edited a document link', 'documents.toggle' => 'Switched a document link on/off', 'documents.destroy' => 'Deleted a document link',
        'gift.give' => 'Handed out a gift', 'gift.undo' => 'Undid a gift',
        'checkin.submit' => 'Self check-in (registration number)', 'checkin.find' => 'Asked for a registration number by email', 'checkin.manual' => 'Checked in an attendee (help desk)', 'checkin.undo' => 'Undid a check-in', 'checkin.settings' => 'Changed check-in options', 'checkin.token' => 'Made a new check-in QR',
        'birthdays.toggle' => 'Switched birthday wishes on/off', 'birthdays.settings' => 'Changed birthday message & schedule', 'birthdays.wishes' => 'Changed birthday wishes for a participant',
        'birthdays.send' => 'Sent a birthday card', 'birthdays.run' => "Sent today's birthday cards", 'birthdays.resend' => 'Resent a birthday card',
        'birthdays.cards.upload' => 'Uploaded a birthday card design', 'birthdays.cards.update' => 'Edited a birthday card design', 'birthdays.cards.default' => 'Changed the default birthday card', 'birthdays.cards.destroy' => 'Deleted a birthday card design',
        'gift.status' => 'Handed out a gift', 'verifydata' => 'Checked in an attendee', 'email-template.test' => 'Sent a test email', 'email-template.reset' => 'Reset the email template',
    ];

    private const VERBS = ['store' => 'Created', 'update' => 'Edited', 'destroy' => 'Deleted', 'delete' => 'Deleted', 'toggle' => 'Switched on/off', 'reorder' => 'Reordered',
        'order' => 'Reordered', 'upload' => 'Uploaded a file for', 'duplicate' => 'Duplicated', 'move' => 'Moved', 'settings' => 'Changed settings of', 'action' => 'Changed', 'banner' => 'Changed banner of'];

    /** Routes that change nothing (previews, checks) — not logged. */
    private const SKIP = ['birthdays.preview-email', 'certificates.preview', 'card-print.check', 'raffle.email.preview', 'email-template.preview', 'profile.track', 'login.custom', 'logout'];

    /** Route ids that are not model-bound: table and columns for a readable label. */
    private const LOOKUP = ['participant' => ['participants', ['code', 'first_name', 'last_name']], 'registration' => ['registrations', ['qr_code', 'firstname', 'lastname']],
        'id' => ['registrations', ['qr_code', 'firstname', 'lastname']]];

    private const SECRET = '/pass|token|secret|key|otp|code_hash|smtp_pass|credential/i';

    /** GET routes that change data. */
    public const GET_WRITES = ['gift.status', 'verifydata'];

    public static function shouldLog(Request $request): bool
    {
        $name = optional($request->route())->getName();
        if ($name && in_array($name, static::SKIP, true)) return false;
        if ($name && str_starts_with($name, 'my.')) return false;              // participants: see participant_changes
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) return $name && in_array($name, static::GET_WRITES, true);
        return true;
    }

    /** Record an admin request after it ran. */
    public static function fromRequest(Request $request, int $status): void
    {
        $user = Auth::guard('web')->user();
        if (!$user) return;
        $route = $request->route();
        $name = $route?->getName();
        $params = $route ? $route->parameters() : [];
        static::write([
            'user_id' => $user->id, 'user_name' => $user->name,
            'area' => static::area($name, $request->path()), 'action' => static::action($name, $request, $params),
            'route' => $name ? Str::limit($name, 120, '') : Str::limit($request->path(), 120, ''), 'method' => $request->method(),
            'subject' => static::subject($params), 'details' => static::details($request), 'status' => $status,
            'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }

    /** Anything else worth recording (sign-ins, …). */
    public static function event(string $area, string $action, ?object $user = null, ?array $details = null, ?string $subject = null): void
    {
        $r = request();
        static::write(['user_id' => $user->id ?? null, 'user_name' => $user->name ?? null, 'area' => $area, 'action' => $action, 'route' => null,
            'method' => null, 'subject' => $subject, 'details' => $details, 'status' => null, 'ip' => $r?->ip(), 'user_agent' => $r?->userAgent()]);
    }

    private static function write(array $row): void
    {
        try {
            $row['details'] = $row['details'] ? json_encode($row['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $row['user_agent'] = $row['user_agent'] ? Str::limit($row['user_agent'], 250, '') : null;
            $row['ip'] = $row['ip'] ? Str::limit($row['ip'], 45, '') : null;
            $row['created_at'] = now();
            DB::table('audit_logs')->insert($row);
            if (random_int(1, 300) === 1) static::prune();
        } catch (\Throwable $e) {
            // the log must never break the admin page
            Log::warning('BICS audit log failed', ['error' => $e->getMessage()]);
        }
    }

    public static function prune(): int
    {
        $days = PlatformSettings::get('audit_days');
        return $days > 0 ? DB::table('audit_logs')->where('created_at', '<', now()->subDays($days))->delete() : 0;
    }

    public static function area(?string $name, string $path): string
    {
        $first = $name ? explode('.', $name)[0] : explode('/', $path)[0];
        if (in_array($name, ['gift.status', 'verifydata'], true)) return 'attendees';
        if (in_array($first, ['email-attachments'], true)) return 'email-template';
        if ($first === 'gallery' || $first === 'menu') return 'cms';
        if ($first === 'prize' || str_starts_with($path, 'raffle')) return 'raffle';
        if ($first === 'backups') return 'system';
        if (in_array($path, ['delete_register'], true) || str_starts_with($path, 'delete_register')) return 'registrations';
        return array_key_exists($first, static::AREAS) ? $first : 'other';
    }

    private static function action(?string $name, Request $request, array $params): string
    {
        if ($name && isset(static::ACTIONS[$name])) return static::ACTIONS[$name];
        if (str_starts_with($request->path(), 'delete_register')) return 'Deleted a registration';
        if ($name === 'nfc.action') return 'NFC card: ' . str_replace('_', ' ', (string) ($params['action'] ?? 'change'));
        if (!$name) return ucfirst(strtolower($request->method())) . ' ' . $request->path();
        $parts = explode('.', $name);
        $verb = static::VERBS[end($parts)] ?? ucfirst(str_replace('-', ' ', end($parts)));
        $thing = count($parts) > 2 ? $parts[count($parts) - 2] : $parts[0];
        $thing = str_replace(['-', '_'], ' ', Str::singular($thing));
        $thing = ['cm' => 'website page', 'cms' => 'website page', 'section' => 'page section', 'album' => 'gallery album', 'photo' => 'gallery photo',
            'card studio' => 'card template', 'form option' => 'form option', 'menu' => 'menu item', 'email template' => 'email template', 'email attachment' => 'email attachment'][$thing] ?? $thing;
        return $verb . ' ' . $thing;
    }

    /** "participant #12 (Dr Sarah Rahman)" from the route's records / ids. */
    private static function subject(array $params): ?string
    {
        $out = [];
        foreach ($params as $k => $v) {
            if ($k === 'action' || $k === 'direction') continue;
            if (is_object($v) && method_exists($v, 'getKey')) {
                $label = $v->name ?? $v->title ?? $v->event_short ?? $v->card_no ?? $v->code ?? null;
                $out[] = str_replace(['-', '_'], ' ', $k) . ' #' . $v->getKey() . ($label ? ' (' . Str::limit((string) $label, 50) . ')' : '');
            } elseif (is_numeric($v) && isset(static::LOOKUP[$k])) {
                [$table, $cols] = static::LOOKUP[$k];
                $row = DB::table($table)->where('id', (int) $v)->first($cols);
                $label = $row ? trim(implode(' ', array_filter(array_map(fn ($c) => $row->$c, $cols)))) : null;
                $out[] = ($k === 'id' ? 'registration' : $k) . ' #' . $v . ($label ? ' (' . Str::limit($label, 50) . ')' : '');
            } elseif (is_scalar($v)) {
                $out[] = str_replace(['-', '_'], ' ', $k) . ' ' . (is_numeric($v) ? '#' : '') . Str::limit((string) $v, 60);
            }
        }
        return $out ? Str::limit(implode(', ', $out), 120, '') : null;
    }

    /** The fields sent, without secrets; long values and lists shortened. */
    public static function details(Request $request): ?array
    {
        $in = $request->except(['_token', '_method']);
        $out = [];
        foreach (array_slice($in, 0, 30, true) as $k => $v) {
            if (preg_match(static::SECRET, (string) $k)) { $out[$k] = '••••'; continue; }
            $out[$k] = static::value($v, 0);
        }
        foreach ($request->allFiles() as $k => $f) $out[$k] = is_array($f) ? count($f) . ' file(s)' : 'file: ' . Str::limit($f->getClientOriginalName(), 80);
        return $out ?: null;
    }

    private static function value($v, int $depth)
    {
        if (is_null($v) || is_bool($v) || is_int($v) || is_float($v)) return $v;
        if (is_string($v)) return Str::limit(strip_tags($v), 160);
        if (is_array($v)) {
            if ($depth >= 1 || count($v) > 12) return '(' . count($v) . ' items)';
            $o = [];
            foreach (array_slice($v, 0, 12, true) as $k => $x) $o[$k] = preg_match(static::SECRET, (string) $k) ? '••••' : static::value($x, $depth + 1);
            return $o;
        }
        return '(…)';
    }
}
