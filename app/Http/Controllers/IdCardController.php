<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registration › ID Cards (permission "idcards.assign"): give attendees their ID card on the participant
 * dashboard (/my → My events), per event / venue. Nothing is emailed; the card PDF is the usual attendee ID card.
 */
class IdCardController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            \App\Support\EventLock::ensureOpen($this->event($request), 'idcard');
            return $next($request);
        })->only(['assign']);
    }

    private function event(Request $request): Event
    {
        $e = $request->filled('event') ? Event::find((int) $request->input('event')) : null;
        return $e ?: (\App\Support\PinnedEvent::event() ?? Event::orderByDesc('id')->firstOrFail());   // opens on the pinned event
    }

    /** Registrations of the event (newer ones carry event_id, older ones only the venue code). */
    private function regs(Event $event)
    {
        return DB::table('registrations as r')->where(fn ($q) => $q->where('r.event_id', $event->id)->orWhere(fn ($v) => $v->whereNull('r.event_id')->where('r.venue', $event->venue_code)));
    }

    public function index(Request $request){
        $event = $this->event($request);
        return view('backend.pages.idcards', [
            'event' => $event,
            'events' => Event::orderByDesc('is_current')->orderByDesc('event_start')->orderByDesc('id')->get(['id', 'event_short', 'venue_code', 'is_current']),
        ]);
    }

    public function data(Request $request){
        $event = $this->event($request);
        $rows = $this->regs($event)->leftJoin('participants as p', 'p.id', '=', 'r.participant_id')
            ->orderBy('r.firstname')->orderBy('r.lastname')
            ->get(['r.id', 'r.title', 'r.firstname', 'r.lastname', 'r.email', 'r.country', 'r.qr_code', 'r.status', 'r.verify_status', 'r.idcard_assigned_at', 'r.participant_id', 'p.code as participant_code', 'p.email as p_email'])
            ->map(fn ($r) => [
                'id' => $r->id, 'name' => trim(($r->title ? $r->title . ' ' : '') . $r->firstname . ' ' . $r->lastname), 'email' => $r->email, 'country' => $r->country,
                'pin' => $r->qr_code, 'status' => $r->status ?: 'Pending', 'checked_in' => $r->verify_status === 'Yes',
                'assigned_at' => $r->idcard_assigned_at ? substr($r->idcard_assigned_at, 0, 16) : null,
                // the dashboard needs a participant profile with an email to sign in with
                'profile' => $r->participant_code, 'can_sign_in' => (bool) $r->p_email,
                'pdf' => route('registrations.id-card', $r->id),
            ])->values();
        return response()->json(['rows' => $rows]);
    }

    /** Assign / remove ID cards for some registrations of the event, or for every approved one ("all_approved"). */
    public function assign(Request $request){
        $event = $this->event($request);
        $data = $request->validate([
            'ids' => 'nullable|array|max:5000', 'ids.*' => 'integer', 'assign' => 'required|boolean', 'all_approved' => 'nullable|boolean',
        ]);
        $q = $this->regs($event);
        if (!empty($data['all_approved'])) $q->where('r.status', 'Approved');
        elseif (!empty($data['ids'])) $q->whereIn('r.id', $data['ids']);
        else return response()->json(['message' => 'Choose at least one attendee.'], 422);
        $ids = $q->pluck('r.id');
        if ($ids->isEmpty()) return response()->json(['message' => 'Nobody here is registered for ' . $event->event_short . '.'], 422);
        if ($data['assign']) {
            $n = DB::table('registrations')->whereIn('id', $ids)->whereNull('idcard_assigned_at')->update(['idcard_assigned_at' => now(), 'idcard_assigned_by' => $request->user()->id]);
            $msg = $n . ' ID card' . ($n === 1 ? '' : 's') . ' assigned for ' . $event->event_short . '.' . ($n < $ids->count() ? ' ' . ($ids->count() - $n) . ' already had theirs.' : '');
        } else {
            $n = DB::table('registrations')->whereIn('id', $ids)->whereNotNull('idcard_assigned_at')->update(['idcard_assigned_at' => null, 'idcard_assigned_by' => null]);
            $msg = $n . ' ID card' . ($n === 1 ? '' : 's') . ' removed from participant dashboards.';
        }
        return response()->json(['success' => true, 'count' => $n, 'message' => $msg]);
    }
}
