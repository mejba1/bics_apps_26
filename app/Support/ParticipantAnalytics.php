<?php

namespace App\Support;

use App\Models\Event;
use App\Models\NfcCard;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for Reports › Participant analytics: profile visits by source (link / QR / NFC), contacts saved,
 * top profiles, participants by event / organization / category / country, dashboard use, NFC cards and printing.
 * Only daily counts exist for visits (no visitor data is stored).
 */
class ParticipantAnalytics
{
    public const PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months', 0 => 'All time'];

    public int $days;
    public ?Carbon $from;
    public ?int $eventId;

    public function __construct(int $days = 30, ?int $eventId = null)
    {
        $this->days = array_key_exists($days, static::PERIODS) ? $days : 30;
        $this->from = $this->days ? now()->subDays($this->days - 1)->startOfDay() : null;
        $this->eventId = $eventId ?: null;
    }

    /** Participant ids of the chosen event (null = everyone). */
    private function eventIds()
    {
        if (!$this->eventId || !($e = Event::find($this->eventId))) return null;
        return DB::table('registrations')->whereNotNull('participant_id')
            ->where(fn ($w) => $w->where('event_id', $e->id)->orWhere(fn ($v) => $v->whereNull('event_id')->where('venue', $e->venue_code)))->select('participant_id');
    }

    private function people()
    {
        $q = DB::table('participants')->whereNull('deleted_at');
        if ($ids = $this->eventIds()) $q->whereIn('id', $ids);
        return $q;
    }

    private function stats()
    {
        $q = DB::table('profile_stats');
        if ($this->from) $q->where('day', '>=', $this->from->toDateString());
        if ($ids = $this->eventIds()) $q->whereIn('participant_id', $ids);
        return $q;
    }

    public function kpis(): array
    {
        $p = $this->people();
        $m = $this->stats()->select('metric', DB::raw('SUM(count) as n'))->groupBy('metric')->pluck('n', 'metric')->map(fn ($n) => (int) $n);
        $clicks = $m->filter(fn ($n, $k) => str_starts_with($k, 'click_'))->sum();
        $total = (clone $p)->count();
        $active = (clone $p)->where('status', 'active')->count();
        $ids = $this->eventIds();
        $cards = NfcCard::query()->when($ids, fn ($q) => $q->whereIn('participant_id', $ids))->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');
        return [
            'participants' => $total, 'active' => $active, 'suspended' => $total - $active,
            'photo' => (clone $p)->whereNotNull('photo')->count(), 'email' => (clone $p)->whereNotNull('email')->count(),
            'dashboard' => (clone $p)->whereNotNull('last_login_at')->count(),
            'dashboard_recent' => (clone $p)->where('last_login_at', '>=', now()->subDays(30))->count(),
            'password' => (clone $p)->whereNotNull('password')->count(),
            'views' => ($m['view_link'] ?? 0) + ($m['view_qr'] ?? 0) + ($m['view_nfc'] ?? 0),
            'view_link' => $m['view_link'] ?? 0, 'view_qr' => $m['view_qr'] ?? 0, 'view_nfc' => $m['view_nfc'] ?? 0,
            'vcard' => $m['vcard'] ?? 0, 'clicks' => $clicks, 'qr_download' => $m['qr_download'] ?? 0,
            'clicks_by' => $m->filter(fn ($n, $k) => str_starts_with($k, 'click_'))->mapWithKeys(fn ($n, $k) => [substr($k, 6) => $n])->sortDesc()->all(),
            'cards_active' => (int) ($cards['active'] ?? 0), 'cards_assigned' => (int) ($cards['assigned'] ?? 0),
            'cards_off' => (int) (($cards['lost'] ?? 0) + ($cards['damaged'] ?? 0) + ($cards['deactivated'] ?? 0)), 'cards_stock' => (int) ($cards['available'] ?? 0),
            'printed' => DB::table('card_print_job_items')->whereNotNull('printed_at')->when($ids, fn ($q) => $q->whereIn('participant_id', $ids))->distinct()->count('participant_id'),
            'edits' => DB::table('participant_changes')->where('source', 'participant')->when($this->from, fn ($q) => $q->where('created_at', '>=', $this->from))
                ->when($ids, fn ($q) => $q->whereIn('participant_id', $ids))->count(),
        ];
    }

    /** Views per day (or month for long periods) by source, and dashboard sign-ins. */
    public function series(): array
    {
        $monthly = $this->days === 0 || $this->days > 90;
        $fmt = $monthly ? '%Y-%m' : '%Y-%m-%d';
        $rows = $this->stats()->whereIn('metric', ['view_link', 'view_qr', 'view_nfc', 'vcard'])
            ->select(DB::raw("DATE_FORMAT(day, '$fmt') as k"), 'metric', DB::raw('SUM(count) as n'))->groupBy('k', 'metric')->get();
        $logins = DB::table('participant_logins')->when($this->from, fn ($q) => $q->where('created_at', '>=', $this->from))
            ->when($this->eventIds(), fn ($q, $ids) => $q->whereIn('participant_id', $ids))
            ->select(DB::raw("DATE_FORMAT(created_at, '$fmt') as k"), DB::raw('COUNT(*) as n'))->groupBy('k')->pluck('n', 'k');
        // continuous axis
        $start = $this->from ?: Carbon::parse(DB::table('profile_stats')->min('day') ?? now())->startOfMonth();
        if ($monthly) $start = $start->copy()->startOfMonth();
        $keys = [];
        for ($d = $start->copy(); $d->lte(now()); $monthly ? $d->addMonth() : $d->addDay()) $keys[] = $d->format($monthly ? 'Y-m' : 'Y-m-d');
        $by = [];
        foreach ($rows as $r) $by[$r->metric][$r->k] = (int) $r->n;
        $label = fn ($k) => $monthly ? Carbon::createFromFormat('Y-m', $k)->format('M Y') : Carbon::parse($k)->format('j M');
        return [
            'labels' => array_map($label, $keys), 'keys' => $keys, 'monthly' => $monthly,
            'link' => array_map(fn ($k) => $by['view_link'][$k] ?? 0, $keys), 'qr' => array_map(fn ($k) => $by['view_qr'][$k] ?? 0, $keys),
            'nfc' => array_map(fn ($k) => $by['view_nfc'][$k] ?? 0, $keys), 'vcard' => array_map(fn ($k) => $by['vcard'][$k] ?? 0, $keys),
            'logins' => array_map(fn ($k) => (int) ($logins[$k] ?? 0), $keys),
        ];
    }

    public function top(int $limit = 15): array
    {
        $views = $this->stats()->select('participant_id',
            DB::raw("SUM(CASE WHEN metric LIKE 'view_%' THEN count ELSE 0 END) as views"),
            DB::raw("SUM(CASE WHEN metric = 'view_qr' THEN count ELSE 0 END) as qr"), DB::raw("SUM(CASE WHEN metric = 'view_nfc' THEN count ELSE 0 END) as nfc"),
            DB::raw("SUM(CASE WHEN metric = 'view_link' THEN count ELSE 0 END) as link"), DB::raw("SUM(CASE WHEN metric = 'vcard' THEN count ELSE 0 END) as vcard"),
            DB::raw("SUM(CASE WHEN metric LIKE 'click_%' THEN count ELSE 0 END) as clicks"))
            ->groupBy('participant_id')->orderByDesc('views')->limit($limit)->get();
        $people = DB::table('participants')->whereIn('id', $views->pluck('participant_id'))->get(['id', 'code', 'title', 'first_name', 'last_name', 'organization', 'category', 'photo'])->keyBy('id');
        return $views->filter(fn ($v) => isset($people[$v->participant_id]) && $v->views + $v->vcard > 0)->map(function ($v) use ($people) {
            $p = $people[$v->participant_id];
            return ['id' => $p->id, 'code' => $p->code, 'name' => trim(($p->title ? $p->title . ' ' : '') . $p->first_name . ' ' . $p->last_name), 'organization' => $p->organization,
                'category' => $p->category, 'photo' => $p->photo && is_file(base_path($p->photo)) ? Thumb::url($p->photo, 480) : null,
                'views' => (int) $v->views, 'link' => (int) $v->link, 'qr' => (int) $v->qr, 'nfc' => (int) $v->nfc, 'vcard' => (int) $v->vcard, 'clicks' => (int) $v->clicks];
        })->values()->all();
    }

    /** Active participants by a profile field: [[value, count], …] — the rest folded into "Other" after $limit. */
    public function by(string $field, int $limit = 12): array
    {
        abort_unless(in_array($field, ['organization', 'category', 'country'], true), 404);
        $rows = $this->people()->where('status', 'active')->select(DB::raw("COALESCE(NULLIF(TRIM($field), ''), '(not set)') as v"), DB::raw('COUNT(*) as n'))
            ->groupBy('v')->orderByDesc('n')->get();
        $top = $rows->take($limit)->map(fn ($r) => [$r->v, (int) $r->n])->all();
        $rest = $rows->slice($limit)->sum('n');
        if ($rest) $top[] = ['Other (' . ($rows->count() - $limit) . ')', (int) $rest];
        return ['rows' => $top, 'distinct' => $rows->count(), 'all' => $rows->map(fn ($r) => [$r->v, (int) $r->n])->all()];
    }

    /** Registrations, linked participants and check-ins per event. */
    public function events(): array
    {
        $events = Event::orderByDesc('event_start')->orderByDesc('id')->get(['id', 'event_short', 'event_name', 'venue_code', 'event_start']);
        return $events->map(function ($e) {
            $q = DB::table('registrations')->where(fn ($w) => $w->where('event_id', $e->id)->orWhere(fn ($v) => $v->whereNull('event_id')->where('venue', $e->venue_code)));
            return ['id' => $e->id, 'event' => $e->event_short, 'name' => $e->event_name, 'registrations' => (clone $q)->count(),
                'participants' => (clone $q)->whereNotNull('participant_id')->distinct()->count('participant_id'),
                'checked_in' => (clone $q)->where('verify_status', 'Yes')->count(),
                'certificates' => DB::table('certificates')->where('event_id', $e->id)->count(),
                'prizes' => DB::table('winners')->where('event_id', $e->id)->count()];
        })->filter(fn ($r) => $r['registrations'] > 0)->values()->all();
    }
}
