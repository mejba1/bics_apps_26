<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\Raffle;
use App\Support\WinnerEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin > Raffle Draw > Prize Winners (permission "raffle.prizes"): every winner of every event,
 * filtered / sorted by event (venue), with winner emails: Off / Automatic / Manual per event,
 * Send / Send again per winner, and "email everyone not emailed yet". AJAX.
 */
class PrizeWinnerController extends Controller
{
    public function index(Request $request){
        return view('backend.pages.prize-winner', [
            'events' => $this->events(),
            'selected' => $request->has('event') ? ((int) $request->query('event') ?: null) : optional(\App\Support\PinnedEvent::event())->id,
            'winners' => $this->winners(),
            'modes' => WinnerEmail::MODES,
        ]);
    }

    public function data(){
        return response()->json(['events' => $this->events(), 'winners' => $this->winners()]);
    }

    /** Off / Automatic / Manual for one event (the texts stay as they are). */
    public function emailMode(Request $request){
        $data = $request->validate(['event_id' => 'required|integer|exists:events,id', 'mode' => ['required', Rule::in(array_keys(WinnerEmail::MODES))]]);
        $event = Event::findOrFail($data['event_id']);
        WinnerEmail::save($event, ['mode' => $data['mode']] + WinnerEmail::settings($event));
        return response()->json(['success' => true, 'message' => [
            'off' => 'Winner emails are off for ' . $event->event_short . '.',
            'auto' => 'Winners of ' . $event->event_short . ' get an email when the host confirms them.',
            'manual' => 'Manual: send each ' . $event->event_short . ' winner their email from this page.',
        ][$data['mode']], 'events' => $this->events(), 'winners' => $this->winners()]);
    }

    public function emailOne(int $winner){
        $w = DB::table('winners')->where('id', $winner)->first();
        abort_unless($w, 404);
        if (WinnerEmail::settings($w->event_id ? Event::find($w->event_id) : null)['mode'] === 'off') {
            return response()->json(['message' => 'Winner emails are off for this event. Choose Automatic or Manual first.'], 422);
        }
        $error = WinnerEmail::send($w->id);
        return response()->json([
            'success' => !$error, 'message' => $error ? 'Not sent: ' . $error : 'Email sent to ' . $w->winners_name . '.',
            'winners' => $this->winners(),
        ], $error ? 500 : 200);
    }

    /** Email every winner of an event who has not received the email yet. */
    public function emailPending(Request $request){
        $event = Event::findOrFail($request->validate(['event_id' => 'required|integer|exists:events,id'])['event_id']);
        if (WinnerEmail::settings($event)['mode'] === 'off') return response()->json(['message' => 'Winner emails are off for this event.'], 422);
        $ids = DB::table('winners')->where('event_id', $event->id)->whereNull('emailed_at')->pluck('id');
        $sent = 0; $failed = 0;
        foreach ($ids as $id) WinnerEmail::send($id) ? $failed++ : $sent++;
        return response()->json(['success' => true, 'message' => $sent . ' sent' . ($failed ? ', ' . $failed . ' failed (see the red notes)' : '') . '.', 'winners' => $this->winners()]);
    }

    /** CSV of the winners (all events, or ?event=ID). */
    public function export(Request $request){
        $eventId = (int) $request->query('event', 0);
        $rows = collect($this->winners())->when($eventId, fn ($c) => $c->where('event_id', $eventId));
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Event', 'Venue code', 'Prize title', 'Prize', 'Winner', 'Registration number', 'Profession', 'Country', 'Email', 'Drawn at', 'Drawn by', 'Winner email']);
        foreach ($rows as $w) {
            fputcsv($out, [$w['event'], $w['venue'], $w['prize_type'], $w['prize'], $w['name'], $w['number'], $w['profession'], $w['country'], $w['email'],
                $w['drawn_at'], $w['drawn_by'], $w['emailed_at'] ? 'Sent ' . $w['emailed_at'] : ($w['email_error'] ? 'Failed: ' . $w['email_error'] : 'Not sent')]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="prize-winners' . ($eventId ? '-' . $eventId : '') . '-' . date('Ymd') . '.csv"']);
    }

    private function events(): array
    {
        $counts = DB::table('winners')->select('event_id', DB::raw('COUNT(*) as n'))->groupBy('event_id')->pluck('n', 'event_id');
        $prizes = DB::table('prizes')->select('event_id', DB::raw('COUNT(*) as n'))->groupBy('event_id')->pluck('n', 'event_id');
        return Event::orderByDesc('is_current')->orderByDesc('event_start')->orderByDesc('id')->get()
            ->map(fn ($e) => ['id' => $e->id, 'short' => $e->event_short, 'name' => $e->event_name, 'venue' => $e->venue_code, 'current' => (bool) $e->is_current,
                'winners' => (int) ($counts[$e->id] ?? 0), 'prizes' => (int) ($prizes[$e->id] ?? 0), 'email_mode' => WinnerEmail::settings($e)['mode']])
            ->values()->all();
    }

    private function winners(): array
    {
        return DB::table('winners as w')
            ->leftJoin('events as e', 'e.id', '=', 'w.event_id')
            ->leftJoin('registrations as r', 'r.id', '=', 'w.register_id')
            ->leftJoin('raffle_entries as en', 'en.id', '=', 'w.entry_id')
            ->leftJoin('prizes as p', 'p.winner_id', '=', 'w.id')
            ->leftJoin('users as u', 'u.id', '=', 'w.drawn_by')
            ->orderByDesc('w.created_at')->orderByDesc('w.id')
            ->get(['w.id', 'w.event_id', 'w.winners_venue', 'w.winners_name', 'w.winners_number', 'w.winners_designation', 'w.winners_country', 'w.prize', 'w.prize_type',
                'w.created_at', 'w.emailed_at', 'w.email_error', 'e.event_short', 'r.title', 'r.email', 'en.email as entry_email', 'p.sort', 'u.name as drawn_by'])
            ->map(fn ($w) => [
                'id' => $w->id, 'event_id' => $w->event_id, 'event' => $w->event_short ?: $w->winners_venue, 'venue' => $w->winners_venue,
                'name' => trim(($w->title ? $w->title . ' ' : '') . $w->winners_name), 'number' => $w->winners_number,
                'profession' => $w->winners_designation, 'country' => $w->winners_country, 'email' => $w->email ?: $w->entry_email,
                'prize' => trim((string) $w->prize, " ()"), 'prize_type' => $w->prize_type, 'order' => $w->sort,
                'drawn_at' => $w->created_at ? substr($w->created_at, 0, 16) : null, 'drawn_by' => $w->drawn_by,
                'emailed_at' => $w->emailed_at ? substr($w->emailed_at, 0, 16) : null, 'email_error' => $w->email_error,
            ])->values()->all();
    }
}
