<?php

namespace App\Support;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The admin bell: recent activity worth a look, built from data that already exists (no extra table).
 * Each person sees what their permissions cover, for their pinned event. "Unread" = newer than the last time they
 * opened the bell (settings "notif_seen.{user id}").
 *
 *   new registrations ................ registrations.view
 *   self / help-desk check-ins ....... attendees.verify
 *   website contact messages ......... website.pages
 *   emails that failed (certificates, birthday cards) ... certificates.manage / birthdays.manage
 *   today's birthdays ................ birthdays.manage
 *   event becoming read-only soon .... event.settings
 */
class AdminNotifications
{
    public const DAYS = 14;
    public const LIMIT = 40;

    public static function seenAt(int $userId): ?Carbon
    {
        $v = DB::table('settings')->where('key', 'notif_seen.' . $userId)->value('value');
        return $v ? Carbon::parse($v) : null;
    }

    public static function markSeen(int $userId): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'notif_seen.' . $userId], ['value' => now()->toDateTimeString(), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget('notif:' . $userId);
    }

    /** [items, unread] — cached for a minute so every admin page stays fast. */
    public static function forUser($user): array
    {
        return Cache::remember('notif:' . $user->id, 60, function () use ($user) {
            $items = static::build($user);
            $seen = static::seenAt($user->id);
            $unread = $seen ? count(array_filter($items, fn ($i) => $i['at']->gt($seen))) : count($items);
            return [array_map(fn ($i) => $i + ['new' => !$seen || $i['at']->gt($seen)], $items), $unread];
        });
    }

    public static function build($user): array
    {
        $since = now()->subDays(static::DAYS);
        $event = PinnedEvent::event();
        $items = [];
        $can = fn ($p) => $user->can($p);
        $evRegs = fn () => $event ? PinnedEvent::registrations($event) : DB::table('registrations');

        if ($can('registrations.view')) {
            // new registrations: one item per day (with names), so a busy day is not 200 rows
            $rows = $evRegs()->where('created_at', '>=', $since)->orderByDesc('created_at')->limit(300)->get(['firstname', 'lastname', 'country', 'created_at']);
            foreach ($rows->groupBy(fn ($r) => substr($r->created_at, 0, 10)) as $day => $g) {
                $names = $g->take(3)->map(fn ($r) => trim($r->firstname . ' ' . $r->lastname))->implode(', ');
                $items[] = ['icon' => 'bi-person-plus-fill', 'tone' => 'brand', 'at' => Carbon::parse($g->first()->created_at),
                    'title' => $g->count() === 1 ? 'New registration' : $g->count() . ' new registrations',
                    'text' => $names . ($g->count() > 3 ? ' and ' . ($g->count() - 3) . ' more' : '') . ($event ? ' · ' . $event->event_short : ''),
                    'url' => url('/home')];
            }
        }
        if ($can('attendees.verify') && $event) {
            $rows = DB::table('checkins as c')->join('registrations as r', 'r.id', '=', 'c.registration_id')->whereIn('c.registration_id', PinnedEvent::registrations($event)->select('id'))
                ->where('c.created_at', '>=', $since)->orderByDesc('c.created_at')->limit(300)->get(['r.firstname', 'r.lastname', 'c.created_at']);
            foreach ($rows->groupBy(fn ($r) => substr($r->created_at, 0, 13)) as $hour => $g) {
                $items[] = ['icon' => 'bi-qr-code-scan', 'tone' => 'green', 'at' => Carbon::parse($g->first()->created_at),
                    'title' => $g->count() === 1 ? trim($g->first()->firstname . ' ' . $g->first()->lastname) . ' checked in' : $g->count() . ' people checked in',
                    'text' => $event->event_short . ($g->count() > 1 ? ' · ' . $g->take(3)->map(fn ($r) => $r->firstname)->implode(', ') . ($g->count() > 3 ? '…' : '') : ''),
                    'url' => route('checkin.desk')];
            }
        }
        if ($can('website.pages')) {
            foreach (DB::table('contact_messages')->where('created_at', '>=', $since)->orderByDesc('id')->limit(20)->get() as $m) {
                $items[] = ['icon' => 'bi-envelope-paper-fill', 'tone' => 'magenta', 'at' => Carbon::parse($m->created_at),
                    'title' => 'Message from ' . $m->name, 'text' => ($m->subject ? $m->subject . ' — ' : '') . \Illuminate\Support\Str::limit($m->message, 80),
                    'url' => route('notifications.index') . '#msg-' . $m->id];
            }
        }
        if ($can('certificates.manage')) {
            $n = DB::table('certificates')->whereNotNull('email_error')->whereNull('emailed_at')->where('updated_at', '>=', $since)->count();
            if ($n) $items[] = ['icon' => 'bi-exclamation-triangle-fill', 'tone' => 'red', 'at' => Carbon::parse(DB::table('certificates')->whereNotNull('email_error')->max('updated_at')),
                'title' => $n . ' certificate ' . \Illuminate\Support\Str::plural('email', $n) . ' failed', 'text' => 'Open Certificates to see why and send again.', 'url' => route('certificates.index')];
        }
        if ($can('birthdays.manage')) {
            $f = DB::table('birthday_greetings')->where('status', 'failed')->where('updated_at', '>=', $since)->count();
            if ($f) $items[] = ['icon' => 'bi-exclamation-triangle-fill', 'tone' => 'red', 'at' => Carbon::parse(DB::table('birthday_greetings')->where('status', 'failed')->max('updated_at')),
                'title' => $f . ' birthday ' . \Illuminate\Support\Str::plural('card', $f) . ' not delivered', 'text' => 'See Birthdays › History to retry.', 'url' => route('birthdays.index', ['tab' => 'history'])];
            $today = rescue(fn () => Birthdays::on(Birthdays::now())->count(), 0, false);
            if ($today) $items[] = ['icon' => 'bi-cake2-fill', 'tone' => 'magenta', 'at' => Birthdays::now()->startOfDay()->utc(),
                'title' => $today . ' ' . \Illuminate\Support\Str::plural('birthday', $today) . ' today', 'text' => Birthdays::enabled() ? 'Cards go out automatically.' : 'Automatic wishes are off — send a custom card?', 'url' => route('birthdays.index')];
        }
        if ($can('event.settings') && $event) {
            $L = EventLock::state($event);
            if (!empty($L['locksAt'])) $items[] = ['icon' => 'bi-hourglass-split', 'tone' => 'amber', 'at' => EventLock::endsAt($event)->utc(),
                'title' => $event->event_short . ' becomes read-only on ' . $L['locksAt'], 'text' => 'Finish check-in corrections, gifts and the raffle before then.', 'url' => route('events.index')];
        }
        usort($items, fn ($a, $b) => $b['at'] <=> $a['at']);
        return array_slice($items, 0, static::LIMIT);
    }

    /** "5 min ago", "Yesterday, 14:20", "3 Oct" */
    public static function ago(Carbon $at): string
    {
        $diff = $at->diffInMinutes(now());
        if ($diff < 1) return 'Just now';
        if ($diff < 60) return $diff . ' min ago';
        if ($diff < 60 * 24 && $at->isToday()) return $at->diffInHours(now()) . ' h ago';
        if ($at->isYesterday()) return 'Yesterday, ' . $at->format('H:i');
        return $at->format('j M');
    }
}
