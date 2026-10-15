<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\CheckIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin › Attendee Activities › Self Check-in: live arrivals, help-desk check-in, posters and the check-in options.
 * Desk: permission "attendees.verify". Options and new QR: "event.settings".
 */
class CheckInDeskController extends Controller
{
    public function __construct()
    {
        // ended / archived events are read-only
        $this->middleware(function ($request, $next) {
            \App\Support\EventLock::ensureOpen($this->event($request), 'checkin');
            return $next($request);
        })->only(['settings', 'token']);
        // checking people in (help desk / undo): the current event only
        $this->middleware(function ($request, $next) {
            \App\Support\EventLock::ensureCurrent($this->event($request), 'checkin');
            return $next($request);
        })->only(['checkIn', 'undo']);
    }

    private function event(Request $request): Event
    {
        $e = ($request->filled('event') ? Event::find($request->integer('event')) : null) ?? \App\Support\PinnedEvent::event();
        abort_unless($e, 404);
        return $e;
    }

    public function index(Request $request){
        $event = $this->event($request);
        $s = CheckIn::settings($event);
        [$open, $closed] = CheckIn::state($event, $s);
        return view('backend.pages.checkin-desk', [
            'event' => $event, 'events' => Event::orderByDesc('is_current')->orderByDesc('id')->get(['id', 'event_short', 'venue_code', 'is_current']),
            's' => $s, 'open' => $open, 'closed' => $closed, 'stats' => CheckIn::stats($event), 'recent' => CheckIn::recent($event),
            'url' => CheckIn::url($event), 'kioskUrl' => CheckIn::url($event, true), 'tz' => CheckIn::tz($event),
            'canSettings' => $request->user()->can('event.settings'),
            'days' => DB::table('checkins')->whereIn('registration_id', CheckIn::registrations($event)->select('id'))->pluck('created_at')
                ->map(fn ($d) => \Carbon\Carbon::parse($d)->timezone(CheckIn::tz($event))->format('Y-m-d'))->unique()->sort()->values(),
            'tab' => in_array($request->query('tab'), ['live', 'poster', 'settings'], true) ? $request->query('tab') : 'live',
        ]);
    }

    /** New arrivals since the last one shown (polled every few seconds). */
    public function feed(Request $request){
        $event = $this->event($request);
        return response()->json(['stats' => CheckIn::stats($event), 'rows' => CheckIn::recent($event, $request->integer('after'), 40), 'open' => CheckIn::state($event)[0]]);
    }

    /** Help desk: find a registration of this event. */
    public function search(Request $request){
        $event = $this->event($request);
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) return response()->json(['data' => []]);
        $rows = CheckIn::registrations($event)->where(fn ($w) => $w->where('qr_code', 'like', "$q%")->orWhere('email', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")
                ->orWhereRaw("CONCAT(firstname,' ',lastname) LIKE ?", ["%$q%"]))
            ->orderBy('firstname')->limit(15)->get(['id', 'title', 'firstname', 'lastname', 'qr_code', 'email', 'country', 'profession', 'status']);
        $in = DB::table('checkins')->whereIn('registration_id', $rows->pluck('id'))->pluck('created_at', 'registration_id');
        $tz = CheckIn::tz($event);
        return response()->json(['data' => $rows->map(fn ($r) => ['id' => $r->id, 'name' => trim(($r->title ? $r->title . ' ' : '') . $r->firstname . ' ' . $r->lastname),
            'number' => $r->qr_code, 'email' => $r->email, 'country' => $r->country, 'profession' => $r->profession, 'status' => $r->status,
            'in' => isset($in[$r->id]) ? \Carbon\Carbon::parse($in[$r->id])->timezone($tz)->format('j M H:i') : null])]);
    }

    public function checkIn(Request $request){
        $event = $this->event($request);
        $r = CheckIn::registrations($event)->where('id', $request->integer('registration_id'))->first();
        if (!$r) return response()->json(['message' => 'Registration not found for this event.'], 404);
        [$new] = CheckIn::confirm($r, 'desk', ['ip' => $request->ip(), 'user_id' => $request->user()->id]);
        return response()->json(['success' => true, 'message' => $new ? trim($r->firstname . ' ' . $r->lastname) . ' is checked in.' : 'Already checked in.']);
    }

    public function undo(Request $request){
        $event = $this->event($request);
        $r = CheckIn::registrations($event)->where('id', $request->integer('registration_id'))->first();
        abort_unless($r, 404);
        CheckIn::undo($r->id);
        return response()->json(['success' => true, 'message' => 'Check-in removed for ' . trim($r->firstname . ' ' . $r->lastname) . '.']);
    }

    public function settings(Request $request){
        $event = $this->event($request);
        $data = $request->validate([
            'enabled' => 'boolean', 'hours' => 'required|in:always,window',
            'opens' => 'nullable|date_format:Y-m-d H:i', 'closes' => 'nullable|date_format:Y-m-d H:i|after:opens',
            'extra' => ['required', Rule::in(array_keys(CheckIn::EXTRA))],
            'location' => 'boolean', 'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180', 'radius' => 'required|integer|between:50,20000',
            'approved_only' => 'boolean', 'welcome' => 'required|string|max:120', 'message' => 'nullable|string|max:240', 'seconds' => 'required|integer|between:3,60',
            'show_photo' => 'boolean', 'show_profession' => 'boolean', 'show_country' => 'boolean', 'show_organization' => 'boolean',
            'forgot' => 'boolean', 'certificate' => 'required|in:event,never', 'sound' => 'boolean',
        ], ['closes.after' => 'The closing time must be after the opening time.', 'opens.date_format' => 'Choose a date and time.', 'closes.date_format' => 'Choose a date and time.']);
        if ($data['hours'] === 'window' && empty($data['opens']) && empty($data['closes'])) {
            return response()->json(['message' => 'Choose when check-in opens and/or closes, or pick "Always open".', 'errors' => ['opens' => ['Required.']]], 422);
        }
        if (!empty($data['location']) && (!is_numeric($data['lat'] ?? null) || !is_numeric($data['lng'] ?? null))) {
            return response()->json(['message' => 'Set the venue position (latitude and longitude) for the location check.', 'errors' => ['lat' => ['Required.']]], 422);
        }
        foreach (['enabled', 'location', 'approved_only', 'show_photo', 'show_profession', 'show_country', 'show_organization', 'forgot', 'sound'] as $b) $data[$b] = $request->boolean($b);
        CheckIn::save($event, $data);
        [$open, $closed] = CheckIn::state($event);
        return response()->json(['success' => true, 'open' => $open, 'message' => $open ? 'Saved. Check-in is open.' : 'Saved. ' . $closed]);
    }

    public function token(Request $request){
        $event = $this->event($request);
        CheckIn::newToken($event);
        return response()->json(['success' => true, 'url' => CheckIn::url($event), 'message' => 'New QR made. Print the new posters — the old ones no longer work.']);
    }

    /** The check-in QR alone, for your own designs: SVG / PNG / JPG / EPS with size, colours and quiet zone. */
    public function qr(Request $request, string $format){
        $event = $this->event($request);
        abort_unless(isset(\App\Support\QrImage::FORMATS[$format]), 404);
        $kiosk = $request->boolean('kiosk');
        $data = \App\Support\QrImage::make(CheckIn::url($event, $kiosk), $format, $request->only(['size', 'fg', 'bg', 'transparent', 'margin', 'ecc']));
        $name = \Illuminate\Support\Str::slug($event->event_short) . '-checkin' . ($kiosk ? '-kiosk' : '') . '-qr.' . $format;
        return response($data, 200, ['Content-Type' => \App\Support\QrImage::FORMATS[$format], 'Cache-Control' => 'no-store',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline') . '; filename="' . $name . '"']);
    }

    /** Excel report: summary, everyone checked in, and who has not arrived (optionally one day only). */
    public function export(Request $request){
        $event = $this->event($request);
        $tz = CheckIn::tz($event);
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('day')) ? $request->query('day') : null;
        $regs = CheckIn::registrations($event);
        $rows = DB::table('checkins as c')->join('registrations as r', 'r.id', '=', 'c.registration_id')->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->leftJoin('participants as p', 'p.id', '=', 'r.participant_id')
            ->whereIn('c.registration_id', (clone $regs)->select('id'))
            ->when($day, fn ($q) => $q->whereBetween('c.created_at', [\Carbon\Carbon::parse($day, $tz)->startOfDay()->utc(), \Carbon\Carbon::parse($day, $tz)->endOfDay()->utc()]))
            ->orderBy('c.created_at')
            ->get(['c.*', 'r.qr_code', 'r.title', 'r.firstname', 'r.lastname', 'r.email', 'r.phone', 'r.country', 'r.profession', 'r.specialty', 'r.institute', 'r.designation', 'r.gender', 'p.code as pcode', 'u.name as by']);
        $shared = DB::table('checkins')->where('event_id', $event->id)->whereNotNull('device')->select('device', DB::raw('count(*) n'))->groupBy('device')->having('n', '>', 1)->pluck('n', 'device');
        $method = ['self' => 'Own phone (QR)', 'kiosk' => 'Kiosk', 'desk' => 'Help desk'];
        $in = [];
        foreach ($rows as $i => $c) {
            $at = \Carbon\Carbon::parse($c->created_at)->timezone($tz);
            $in[] = [$i + 1, $at->format('Y-m-d'), $at->format('H:i:s'), $method[$c->method] ?? $c->method, $c->by, $c->qr_code, $c->title, $c->firstname, $c->lastname,
                $c->email, $c->phone, $c->country, $c->profession, $c->specialty, $c->institute, $c->designation, $c->gender, $c->pcode,
                $c->distance_m, $c->device && isset($shared[$c->device]) ? $shared[$c->device] : null];
        }
        $checkedIds = DB::table('checkins')->whereIn('registration_id', (clone $regs)->select('id'))->pluck('registration_id');
        $missing = (clone $regs)->whereNotIn('id', $checkedIds)->orderBy('firstname')->get(['qr_code', 'title', 'firstname', 'lastname', 'email', 'phone', 'country', 'profession', 'institute', 'status']);
        $not = $missing->map(fn ($r, $i) => [$i + 1, $r->qr_code, $r->title, $r->firstname, $r->lastname, $r->email, $r->phone, $r->country, $r->profession, $r->institute, $r->status])->all();

        // summary
        $st = CheckIn::stats($event);
        $byDay = DB::table('checkins')->whereIn('registration_id', (clone $regs)->select('id'))->get(['created_at'])
            ->groupBy(fn ($c) => \Carbon\Carbon::parse($c->created_at)->timezone($tz)->format('Y-m-d'))->map->count()->sortKeys();
        $byHour = $rows->groupBy(fn ($c) => \Carbon\Carbon::parse($c->created_at)->timezone($tz)->format('H') . ':00')->map->count()->sortKeys();
        $byCountry = $rows->groupBy(fn ($c) => $c->country ?: '—')->map->count()->sortDesc();
        $sum = [
            [['Check-in report — ' . $event->event_name, 2]],
            [$event->event_venue . ($event->event_start ? ' · ' . \Carbon\Carbon::parse($event->event_start, $tz)->format('j F Y') : '')],
            ['Made ' . now($tz)->format('j F Y, H:i') . ' (' . $tz . ')' . ($day ? ' · only ' . \Carbon\Carbon::parse($day)->format('j F Y') : '')],
            [],
            [['Registered', 3], $st['registered']], [['Checked in', 3], $st['checked']], [['Not arrived', 3], $st['waiting']], [['Checked in (%)', 3], $st['percent']],
            [], [['How', 3]], ['Own phone (QR)', $st['self']], ['Kiosk', $st['kiosk']], ['Help desk', $st['desk']],
            [], [['By day', 3]],
        ];
        foreach ($byDay as $d => $n) $sum[] = [\Carbon\Carbon::parse($d)->format('D j M Y'), $n];
        $sum[] = []; $sum[] = [['By hour' . ($day ? '' : ' (all days)'), 3]];
        foreach ($byHour as $h => $n) $sum[] = [$h, $n];
        $sum[] = []; $sum[] = [['By country', 3]];
        foreach ($byCountry as $cty => $n) $sum[] = [$cty, $n];

        $x = (new \App\Support\XlsxWriter())
            ->sheet('Summary', ['', ''], $sum, ['' => 34], [], false)
            ->sheet('Checked in', ['#', 'Date', 'Time', 'How', 'By (staff)', 'Registration no.', 'Title', 'First name', 'Last name', 'Email', 'Phone', 'Country', 'Profession', 'Speciality', 'Institution', 'Designation', 'Gender', 'Participant ID', 'Distance from venue (m)', 'Same phone used for'],
                $in, ['Email' => 30, 'Institution' => 32], ['#', 'Distance from venue (m)', 'Same phone used for'])
            ->sheet('Not arrived', ['#', 'Registration no.', 'Title', 'First name', 'Last name', 'Email', 'Phone', 'Country', 'Profession', 'Institution', 'Status'], $not, ['Email' => 30, 'Institution' => 32], ['#']);
        $file = \Illuminate\Support\Str::slug($event->event_short) . '-checkin-report-' . ($day ?: now($tz)->format('Y-m-d')) . '.xlsx';
        return $x->download($file);
    }

    /** Printable poster (A4 / A5 / table tent), opened in a new tab and printed from the browser. */
    public function poster(Request $request){
        $event = $this->event($request);
        $kiosk = $request->boolean('kiosk');
        $url = CheckIn::url($event, $kiosk);
        $qr = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(600)->margin(0)->errorCorrection('H')->generate($url);
        return view('checkin.poster', ['event' => $event, 'url' => $url, 'qr' => (string) $qr, 'size' => in_array($request->query('size'), ['a4', 'a5', 'tent'], true) ? $request->query('size') : 'a4',
            'copies' => max(1, min(10, $request->integer('copies', 1))), 'tz' => CheckIn::tz($event)]);
    }
}
