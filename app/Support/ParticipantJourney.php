<?php

namespace App\Support;

use App\Models\Event;
use App\Models\NfcCard;
use App\Models\Participant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything event-related for one participant's dashboard: each registration with its event, approval, check-in,
 * ID card, certificate, raffle prizes and gift; totals (events attended, years with BICS, …), the next event,
 * an activity timeline and milestones.
 */
class ParticipantJourney
{
    public function __construct(public Participant $p)
    {
    }

    private ?Collection $events = null;

    /** One item per registration, newest event first. */
    public function events(): Collection
    {
        if ($this->events !== null) return $this->events;
        $p = $this->p;
        $regs = DB::table('registrations')->where('participant_id', $p->id)->orderByDesc('id')
            ->get(['id', 'event_id', 'venue', 'qr_code', 'firstname', 'lastname', 'status', 'verify_status', 'verify_at', 'gift', 'idcard_assigned_at', 'created_at']);
        $events = Event::whereIn('id', $regs->pluck('event_id')->filter())->orWhereIn('venue_code', $regs->pluck('venue')->filter())->get();
        // only certificates the organizers gave (assigned, or emailed) — not ones merely previewed in the admin
        $certs = DB::table('certificates')->whereIn('registration_id', $regs->pluck('id'))->whereNotNull('assigned_at')->get()->keyBy('registration_id');
        $prizes = DB::table('winners')->whereIn('register_id', $regs->pluck('id'))->orderBy('id')->get()->groupBy('register_id');
        $now = now();
        return $this->events = $regs->map(function ($r) use ($events, $certs, $prizes, $now) {
            $e = $events->firstWhere('id', $r->event_id) ?: $events->firstWhere('venue_code', $r->venue);
            $start = $e?->dateOf('event_start');
            $end = $e?->dateOf('event_end') ?: $start?->copy()->endOfDay();
            $checkedIn = $r->verify_status === 'Yes';
            $cert = $certs[$r->id] ?? null;
            $approved = $r->status === 'Approved';
            // same phases as the admin side (App\Support\EventLock): upcoming · live · ended (archived counts as ended)
            $phase = $e ? EventLock::phase($e) : 'ended';
            if ($phase === 'archived') $phase = 'ended';
            if (in_array($phase, ['draft', 'undated'], true)) $phase = $e && ($e->is_current || $e->status === 'draft') ? 'upcoming' : 'ended';
            $tzNow = $e ? Carbon::now(EventLock::tz($e)) : $now;
            $endsAt = $e ? EventLock::endsAt($e) : null;
            return (object) [
                'id' => $r->id,
                'event' => $e,
                'name' => $e->event_name ?? $r->venue,
                'short' => $e->event_short ?? $r->venue,
                'venue' => $e->event_venue ?? null,
                'dates' => $e ? $e->datesLabel() : '',
                'start' => $start,
                'year' => $start ? $start->year : Carbon::parse($r->created_at)->year,
                'upcoming' => in_array($phase, ['upcoming', 'live'], true),
                'phase' => $phase,
                'live' => $phase === 'live',
                'ended' => $phase === 'ended',
                'ended_on' => $phase === 'ended' && $endsAt ? $endsAt->format('j F Y') : null,
                'days_to' => $phase === 'upcoming' && $start ? max(0, (int) $tzNow->copy()->startOfDay()->diffInDays($start->copy()->startOfDay(), false)) : null,
                'registration_no' => $r->qr_code,
                'registered_at' => $r->created_at ? Carbon::parse($r->created_at) : null,
                'status' => $r->status ?: 'Pending',
                'approved' => $approved,
                'checked_in' => $checkedIn,
                'checked_in_at' => $checkedIn ? static::verifyTime($r->verify_at) : null,
                // the ID card shows once an admin assigned it (Registration › ID Cards)
                'id_card' => $r->idcard_assigned_at && $r->qr_code,
                'id_card_at' => $r->idcard_assigned_at ? Carbon::parse($r->idcard_assigned_at) : null,
                'certificate' => $cert ? (object) ['token' => $cert->token, 'number' => $cert->number, 'issued_at' => Carbon::parse($cert->assigned_at)] : null,
                'prizes' => ($prizes[$r->id] ?? collect())->map(fn ($w) => (object) ['prize' => $w->prize, 'type' => $w->prize_type, 'at' => $w->created_at ? Carbon::parse($w->created_at) : null])->values(),
                // welcome gift, marked at the gift desk (Attendee activities › Distribute gifts)
                'gift' => ['Approved' => 'received', 'Rejected' => 'none'][$r->gift] ?? 'pending',
            ];
        })->sortByDesc(fn ($x) => $x->start ? $x->start->timestamp : ($x->registered_at?->timestamp ?? 0))->values();
    }

    /** "19-02-2026 08:37:08am" (check-in time as stored). */
    public static function verifyTime(?string $v): ?Carbon
    {
        if (!$v) return null;
        foreach (['d-m-Y h:i:sa', 'd-m-Y H:i:s', 'Y-m-d H:i:s'] as $f) {
            try { $d = Carbon::createFromFormat($f, $v); if ($d) return $d; } catch (\Throwable $e) {}
        }
        return null;
    }

    public function totals(): array
    {
        $ev = $this->events();
        $years = $ev->pluck('year')->filter()->unique()->sort()->values();
        $first = $years->first();
        return [
            'registered' => $ev->count(),
            'attended' => $ev->where('checked_in', true)->count(),
            'certificates' => $ev->filter(fn ($e) => $e->certificate)->count(),
            'prizes' => $ev->sum(fn ($e) => $e->prizes->count()),
            'years' => $years->count(),
            'since' => $first,
            'with_us' => $first ? max(1, now()->year - $first) : 0,   // years since the first event (at least 1)
            'age' => BirthDate::age($this->p->birth_date?->toDateString()),
        ];
    }

    public function next(): ?object
    {
        return $this->events()->filter(fn ($e) => $e->upcoming && $e->start)->sortBy(fn ($e) => $e->start->timestamp)->first();
    }

    /** Newest first: registrations, check-ins, certificates, prizes, NFC card, password, profile. */
    public function timeline(int $limit = 10): Collection
    {
        $items = collect();
        foreach ($this->events() as $e) {
            if ($e->registered_at) $items->push(['at' => $e->registered_at, 'icon' => 'bi-pencil-square', 'tone' => 'blue', 'text' => 'Registered for ' . $e->short]);
            if ($e->checked_in_at) $items->push(['at' => $e->checked_in_at, 'icon' => 'bi-door-open', 'tone' => 'green', 'text' => 'Checked in at ' . $e->short]);
            if ($e->id_card_at) $items->push(['at' => $e->id_card_at, 'icon' => 'bi-person-badge', 'tone' => 'teal', 'text' => 'ID card issued for ' . $e->short]);
            if ($e->certificate?->issued_at) $items->push(['at' => $e->certificate->issued_at, 'icon' => 'bi-award', 'tone' => 'gold', 'text' => 'Certificate issued for ' . $e->short]);
            foreach ($e->prizes as $w) if ($w->at) $items->push(['at' => $w->at, 'icon' => 'bi-trophy', 'tone' => 'magenta', 'text' => 'Won ' . ($w->prize ?: 'a prize') . ' at ' . $e->short]);
        }
        $card = NfcCard::where('participant_id', $this->p->id)->whereNotNull('activated_at')->orderByDesc('activated_at')->first();
        if ($card) $items->push(['at' => $card->activated_at, 'icon' => 'bi-broadcast', 'tone' => 'teal', 'text' => 'NFC card ' . $card->card_no . ' activated']);
        if ($this->p->password_changed_at) $items->push(['at' => $this->p->password_changed_at, 'icon' => 'bi-shield-lock', 'tone' => 'gray', 'text' => 'Password changed']);
        if ($this->p->created_at) $items->push(['at' => $this->p->created_at, 'icon' => 'bi-person-plus', 'tone' => 'gray', 'text' => 'Profile created (' . $this->p->code . ')']);
        return $items->sortByDesc(fn ($i) => $i['at']->timestamp)->take($limit)->values();
    }

    /** Small achievements shown on the overview. */
    public function milestones(): array
    {
        $t = $this->totals();
        $m = [];
        if ($t['attended'] >= 1) $m[] = ['bi-door-open', $t['attended'] === 1 ? 'First event attended' : $t['attended'] . ' events attended'];
        if ($t['years'] >= 2) $m[] = ['bi-calendar2-check', $t['years'] . ' years of BICS'];
        if ($t['certificates'] >= 1) $m[] = ['bi-award', $t['certificates'] . ' certificate' . ($t['certificates'] > 1 ? 's' : '')];
        if ($t['prizes'] >= 1) $m[] = ['bi-trophy', 'Prize winner'];
        if (NfcCard::where('participant_id', $this->p->id)->where('status', 'active')->exists()) $m[] = ['bi-broadcast', 'NFC card holder'];
        if ($this->completeness()['percent'] === 100) $m[] = ['bi-patch-check', 'Complete profile'];
        return $m;
    }

    /** How complete the profile is, and what is missing. */
    public function completeness(): array
    {
        $p = $this->p;
        $checks = ['photo' => 'Photo', 'designation' => 'Designation', 'organization' => 'Organization', 'country' => 'Country', 'mobile' => 'Mobile',
            'bio' => 'Biography', 'expertise' => 'Areas of expertise', 'website' => 'Website or social link'];
        $missing = [];
        foreach ($checks as $k => $label) {
            $v = $k === 'website' ? ($p->website || $p->socialLinks()) : $p->getAttribute($k);
            if (!$v) $missing[] = $label;
        }
        return ['percent' => (int) round((count($checks) - count($missing)) / count($checks) * 100), 'missing' => $missing];
    }
}
