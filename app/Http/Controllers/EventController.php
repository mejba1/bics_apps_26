<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\EventSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Events (permission "event.settings"): create, edit, copy, publish / archive, delete events,
 * and choose the CURRENT event (the one the public registration form, raffle and emails use). AJAX.
 *
 * Rules:
 * - there is always exactly one current event; it is always published and cannot be archived or deleted;
 * - an event with registrations cannot be deleted, and its venue code is locked (registrations store it).
 */
class EventController extends Controller
{
    public function index(){
        return view('backend.pages.events', [
            'events' => $this->list(),
            'timezones' => timezone_identifiers_list(),
            'defaultTimezone' => EventSettings::timezone(),
        ]);
    }

    public function store(Request $request){
        $data = $this->validated($request);
        $event = new Event($data);
        $event->status = $data['status'] ?? 'draft';
        DB::transaction(function () use ($event, $request) {
            $event->save();
            if ($request->boolean('make_current') || !Event::where('is_current', true)->where('id', '!=', $event->id)->exists()) $event->makeCurrent();
        });
        EventSettings::forget();
        return response()->json([
            'success' => true,
            'message' => 'Event created.' . ($event->is_current ? ' New registrations now go to ' . $event->event_short . '.' : ''),
            'event' => $this->json($event->fresh()),
            'edit_url' => route('events.edit', $event),
        ]);
    }

    public function edit(Event $event){
        $s = $event->toSettings();
        // datetime-local inputs need "Y-m-d\TH:i"
        foreach (Event::DATES as $k) $s[$k] = $s[$k] ? str_replace(' ', 'T', substr($s[$k], 0, 16)) : '';
        return view('backend.pages.event-settings', [
            'event' => $event,
            'settings' => $s,
            'registrations' => $event->registrations()->count() ?: DB::table('registrations')->where('venue', $event->venue_code)->count(),
            'timezones' => timezone_identifiers_list(),
            'status' => $this->summary($event),
        ]);
    }

    public function update(Request $request, Event $event){
        $data = $this->validated($request, $event);
        if ($event->is_current) unset($data['status']); // the current event stays published
        $event->fill($data)->save();
        EventSettings::forget();
        return response()->json(['success' => true, 'message' => 'Event saved.', 'status' => $this->summary($event), 'event' => $this->json($event)]);
    }

    /** New registrations, the raffle and confirmation emails switch to this event. */
    /** Super Admin: open an ended / archived event for corrections for a while (App\Support\EventLock). */
    public function unlock(Request $request, Event $event){
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a Super Admin can unlock an event.');
        \App\Support\EventLock::unlock($event, $request->user()->id);
        return response()->json(['success' => true, 'message' => $event->event_short . ' is unlocked for ' . \App\Support\EventLock::UNLOCK_HOURS . ' hours.', 'events' => $this->list()]);
    }

    public function lock(Request $request, Event $event){
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a Super Admin can lock an event.');
        \App\Support\EventLock::relock($event);
        return response()->json(['success' => true, 'message' => $event->event_short . ' is read-only again.', 'events' => $this->list()]);
    }

    public function makeCurrent(Event $event){
        $event->makeCurrent();
        EventSettings::forget();
        return response()->json(['success' => true, 'message' => $event->event_short . ' is now the current event.', 'events' => $this->list()]);
    }

    public function status(Request $request, Event $event){
        $data = $request->validate(['status' => 'required|in:draft,published,archived']);
        if ($event->is_current && $data['status'] !== 'published') {
            $this->fail('status', 'This is the current event. Make another event current first.');
        }
        $event->update(['status' => $data['status']]);
        return response()->json(['success' => true, 'message' => 'Event ' . ['draft' => 'moved to draft', 'published' => 'published', 'archived' => 'archived'][$data['status']] . '.', 'events' => $this->list()]);
    }

    public function destroy(Event $event){
        if ($event->is_current) $this->fail('event', 'The current event cannot be deleted. Make another event current first.');
        if ($this->registrationCount($event)) $this->fail('event', 'This event has registrations, so it cannot be deleted. Archive it instead.');
        $event->delete();
        return response()->json(['success' => true, 'message' => 'Event deleted.', 'events' => $this->list()]);
    }

    /** Old address: Admin > Event & Registration opens the current event. */
    public function editCurrent(){
        $event = Event::current();
        return $event ? redirect()->route('events.edit', $event) : redirect()->route('events.index');
    }

    private function validated(Request $request, ?Event $event = null): array
    {
        $data = $request->validate([
            'event_name' => 'required|max:200',
            'event_short' => 'required|max:50',
            'slug' => ['nullable', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('events', 'slug')->ignore($event?->id)],
            'event_venue' => 'nullable|max:255',
            'event_timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'event_start' => 'required|date',
            'event_end' => 'nullable|date|after_or_equal:event_start',
            'registration_opens' => 'nullable|date',
            'registration_closes' => 'nullable|date|after:registration_opens',
            'registration_closed_message' => 'nullable|max:500',
            'registration_upcoming_message' => 'nullable|max:500',
            'venue_code' => ['required', 'max:50', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique('events', 'venue_code')->ignore($event?->id)],
            'tracking_no' => 'nullable|max:50',
            'status' => 'nullable|in:draft,published,archived',
        ], [
            'event_end.after_or_equal' => 'The event cannot end before it starts.',
            'registration_closes.after' => 'Registration must close after it opens.',
            'venue_code.regex' => 'Use letters, numbers, - or _ only (e.g. Spain-26).',
            'venue_code.unique' => 'Another event already uses this code.',
            'slug.regex' => 'Use lowercase letters, numbers and - only (e.g. bics-2027).',
            'slug.unique' => 'Another event already uses this web address.',
        ], [
            'event_name' => 'event name', 'event_short' => 'short name', 'event_start' => 'event start', 'event_end' => 'event end',
            'registration_opens' => 'registration opening', 'registration_closes' => 'registration closing', 'venue_code' => 'event code',
        ]);

        // Registrations store the code, so it cannot change once people have registered
        if ($event && $data['venue_code'] !== $event->venue_code && $this->registrationCount($event)) {
            $this->fail('venue_code', 'People have already registered with this code, so it cannot be changed.');
        }

        // Dates as "Y-m-d H:i" (entered in the event timezone)
        foreach (Event::DATES as $k) $data[$k] = !empty($data[$k]) ? Carbon::parse($data[$k])->format('Y-m-d H:i') : null;
        $data['slug'] = ($data['slug'] ?? null) ?: Event::uniqueSlug($data['event_short'], $event?->id);
        $data['tracking_no'] = ($data['tracking_no'] ?? null) ?: $data['venue_code'] . '-' . Carbon::parse($data['event_start'])->format('Ymd');
        if (empty($data['status'])) unset($data['status']);
        return $data;
    }

    private function registrationCount(Event $event): int
    {
        return DB::table('registrations')->where(fn ($q) => $q->where('event_id', $event->id)->orWhere('venue', $event->venue_code))->count();
    }

    private function list(): array
    {
        $counts = DB::table('registrations')->select('venue', DB::raw('COUNT(*) as n'), DB::raw("SUM(verify_status = 'Yes') as checked"))
            ->groupBy('venue')->get()->keyBy('venue');
        return Event::orderByDesc('is_current')->orderByRaw("FIELD(status, 'draft', 'published', 'archived')")
            ->orderByDesc('event_start')->orderByDesc('id')->get()
            ->map(fn ($e) => $this->json($e, $counts[$e->venue_code] ?? null))->values()->all();
    }

    private function json(Event $e, ?object $count = null): array
    {
        $count = $count ?? DB::table('registrations')->where('venue', $e->venue_code)
            ->select(DB::raw('COUNT(*) as n'), DB::raw("SUM(verify_status = 'Yes') as checked"))->first();
        $window = $e->registrationWindow();
        return $e->only([
            'id', 'event_name', 'event_short', 'slug', 'venue_code', 'tracking_no', 'event_venue', 'event_timezone',
            'event_start', 'event_end', 'registration_opens', 'registration_closes',
            'registration_closed_message', 'registration_upcoming_message', 'status',
        ]) + [
            'is_current' => (bool) $e->is_current,
            'lock' => \Illuminate\Support\Arr::except(\App\Support\EventLock::state($e), ['event']),
            'dates' => $e->datesLabel(),
            'registration_state' => $window['state'],
            'registrations' => (int) ($count->n ?? 0),
            'checked_in' => (int) ($count->checked ?? 0),
            'edit_url' => route('events.edit', $e),
            'dashboard_url' => url('/home?venue=' . urlencode($e->venue_code)),
        ];
    }

    /** Human summary shown at the top of the edit page. */
    private function summary(Event $event): array
    {
        $reg = $event->registrationWindow();
        $fmt = fn ($d) => $d ? $d->format('j M Y, H:i T') : null;
        $text = [
            'open' => $reg['closes'] ? 'Open now — closes ' . $fmt($reg['closes']) : 'Open now — no closing date set',
            'upcoming' => 'Not open yet — opens ' . $fmt($reg['opens']),
            'closed' => 'Closed since ' . $fmt($reg['closes']),
        ][$reg['state']];
        return ['state' => $reg['state'], 'text' => $text, 'event' => $event->datesLabel(), 'now' => Carbon::now($event->timezone())->format('j M Y, H:i T')];
    }

    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
