<?php

namespace App\Http\Controllers;

use App\Models\Prize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class BackendController extends Controller
{
    public function __construct()
    {
        // gifts of an ended / archived event are read-only
        $this->middleware(function ($request, $next) {
            \App\Support\EventLock::ensureCurrent(\App\Support\EventLock::forRegistration((int) $request->route('id')), 'gift');
            return $next($request);
        })->only(['giftGive', 'giftUndo']);
    }

    /* Verify Attendee & Gift Distribution: the pinned event (header), or ?event={id} to look at another one. */
    public function verifyUser(Request $request){
        return $this->attendeePage($request, 'verify');
    }
    public function giftDistribute(Request $request){
        return $this->attendeePage($request, 'gift');
    }

    private function attendeePage(Request $request, string $mode)
    {
        $event = \App\Support\PinnedEvent::forPage($request);
        abort_unless($event, 404, 'Create an event first (Admin > Events).');
        $tz = \App\Support\CheckIn::tz($event);
        $regs = \App\Support\PinnedEvent::registrations($event)->orderByDesc('id')
            ->get(['id', 'qr_code', 'title', 'firstname', 'lastname', 'profession', 'specialty', 'country', 'email', 'phone', 'image', 'participant_id', 'gift', 'status', 'created_at']);
        $ids = $regs->pluck('id');
        $checkins = DB::table('checkins as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')->whereIn('c.registration_id', $ids)
            ->get(['c.registration_id', 'c.created_at', 'c.method', 'u.name as by'])->keyBy('registration_id');
        $gifts = DB::table('gift as g')->leftJoin('users as u', 'u.id', '=', 'g.user_id')->whereIn('g.reg_id', $ids)->orderBy('g.id')
            ->get(['g.reg_id', 'g.create_at', 'u.name as by'])->keyBy('reg_id');
        $photos = DB::table('participants')->whereIn('id', $regs->pluck('participant_id')->filter())->pluck('photo', 'id');
        $when = fn ($t) => $t ? \Carbon\Carbon::parse($t)->timezone($tz)->format('j M, H:i') : null;
        $rows = $regs->map(function ($r) use ($checkins, $gifts, $photos, $when) {
            $pp = $r->participant_id ? ($photos[$r->participant_id] ?? null) : null;
            $photo = $pp && is_file(base_path($pp)) ? \App\Support\Thumb::url($pp, 480)
                : ($r->image && $r->image !== 'no-photo.png' && is_file(base_path('upload/images/' . $r->image)) ? \App\Support\Thumb::url('upload/images/' . $r->image, 480) : null);
            $c = $checkins[$r->id] ?? null; $g = $gifts[$r->id] ?? null;
            return ['id' => $r->id, 'number' => (string) $r->qr_code, 'name' => trim(($r->title ? $r->title . ' ' : '') . $r->firstname . ' ' . $r->lastname),
                'initials' => mb_strtoupper(mb_substr((string) $r->firstname, 0, 1) . mb_substr((string) $r->lastname, 0, 1)),
                'profession' => trim($r->profession . ($r->specialty ? ' · ' . $r->specialty : '')), 'country' => $r->country, 'email' => $r->email, 'phone' => $r->phone,
                'photo' => $photo, 'status' => $r->status, 'registered' => \Carbon\Carbon::parse($r->created_at)->format('j M Y'),
                'in' => $c ? $when($c->created_at) : null, 'in_how' => $c->method ?? null, 'in_by' => $c->by ?? null,
                'gift' => $r->gift === 'Approved', 'gift_at' => $g ? $when($g->create_at) : null, 'gift_by' => $g->by ?? null];
        })->values();
        return view('backend.pages.attendees', [
            'mode' => $mode, 'event' => $event, 'rows' => $rows,
            'pinned' => optional(\App\Support\PinnedEvent::event())->id === $event->id,
            'events' => \App\Models\Event::orderByDesc('is_current')->orderByDesc('event_start')->orderByDesc('id')->get(['id', 'event_short', 'venue_code', 'is_current']),
        ]);
    }

    /** Hand out a gift (POST, so a link preview or a double click never marks anyone). */
    public function giftGive(Request $request, int $id){
        $r = DB::table('registrations')->where('id', $id)->first();
        abort_unless($r, 404);
        if ($r->gift !== 'Approved') {
            DB::table('registrations')->where('id', $id)->update(['gift' => 'Approved']);
            DB::table('attendees')->where('id', $id)->update(['gift' => 'Approved']);   // older copy of the registrations
            DB::table('gift')->insert(['reg_id' => $id, 'user_id' => $request->user()->id, 'ip' => $request->ip(), 'create_at' => now()]);
        }
        return response()->json(['success' => true, 'message' => 'Gift handed to ' . trim($r->firstname . ' ' . $r->lastname) . '.']);
    }

    public function giftUndo(Request $request, int $id){
        $r = DB::table('registrations')->where('id', $id)->first();
        abort_unless($r, 404);
        DB::table('registrations')->where('id', $id)->update(['gift' => 'Pending']);
        DB::table('attendees')->where('id', $id)->update(['gift' => 'Pending']);
        DB::table('gift')->where('reg_id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Gift removed for ' . trim($r->firstname . ' ' . $r->lastname) . '.']);
    }

    /**
     * Several people at once (Verify Attendee / Gift Distribution checkboxes).
     * POST {event, ids[], action: checkin|uncheck (verify) · give|ungive (gift)}. Only registrations of that event are touched.
     */
    public function attendeeBulk(Request $request, string $mode){
        abort_unless($request->user()->can($mode === 'gift' ? 'attendees.gifts' : 'attendees.verify'), 403);
        $data = $request->validate(['event' => 'required|integer', 'ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer',
            'action' => ['required', \Illuminate\Validation\Rule::in($mode === 'gift' ? ['give', 'ungive'] : ['checkin', 'uncheck'])]],
            ['ids.required' => 'Select at least one person.']);
        $event = \App\Models\Event::findOrFail($data['event']);
        \App\Support\EventLock::ensureCurrent($event, $mode === 'gift' ? 'gift' : 'checkin');   // current event only
        $regs = \App\Support\PinnedEvent::registrations($event)->whereIn('id', $data['ids'])->get();
        $done = 0;
        foreach ($regs as $r) {
            switch ($data['action']) {
                case 'checkin':
                    [$new] = \App\Support\CheckIn::confirm($r, 'desk', ['ip' => $request->ip(), 'user_id' => $request->user()->id]);
                    $done += $new ? 1 : 0; break;
                case 'uncheck':
                    $done += \App\Support\CheckIn::undo($r->id) ? 1 : 0; break;
                case 'give':
                    if ($r->gift !== 'Approved') {
                        DB::table('registrations')->where('id', $r->id)->update(['gift' => 'Approved']);
                        DB::table('attendees')->where('id', $r->id)->update(['gift' => 'Approved']);
                        DB::table('gift')->insert(['reg_id' => $r->id, 'user_id' => $request->user()->id, 'ip' => $request->ip(), 'create_at' => now()]);
                        $done++;
                    }
                    break;
                case 'ungive':
                    if ($r->gift === 'Approved') {
                        DB::table('registrations')->where('id', $r->id)->update(['gift' => 'Pending']);
                        DB::table('attendees')->where('id', $r->id)->update(['gift' => 'Pending']);
                        DB::table('gift')->where('reg_id', $r->id)->delete();
                        $done++;
                    }
                    break;
            }
        }
        $foreign = count(array_unique($data['ids'])) - $regs->count();   // not registered for this event
        $already = $regs->count() - $done;
        $what = ['checkin' => 'checked in', 'uncheck' => 'check-ins undone', 'give' => 'gifts handed out', 'ungive' => 'gifts undone'][$data['action']];
        return response()->json(['success' => true, 'done' => $done, 'ids' => $regs->pluck('id'),
            'message' => $done . ' ' . $what . ($already > 0 ? ' · ' . $already . ' already done' : '') . ($foreign > 0 ? ' · ' . $foreign . ' not in ' . $event->event_short : '') . '.']);
    }

    /** Old link (/gift/{id}) changed data on a simple visit; it now just opens the page. */
    public function giftUpdate(Request $request, $id){
        return redirect('/gift-distribute');
    }
    
    public function venue(Request $request, $venue){
        // The dashboard now shows any event (venue) with the same numbers and more
        return redirect('/home?venue=' . urlencode($venue));
    }

    /** Old venue details page (kept for reference; the dashboard replaces it). */
    public function venueLegacy(Request $request, $venue){
    // Retrieve all registrations for the given venue
    $venueData = DB::table('registrations')
        ->where('venue', $venue)
        ->get();

    // Count distinct countries for the venue
    $countryCount = DB::table('registrations')
        ->where('venue', $venue)
        ->distinct()
        ->count('country');

    // Count distinct verified countries for the venue
    $verifiedRegisterCount = DB::table('registrations')
        ->where('venue', $venue)
        ->where('verify_status', 'Yes')
        ->count();
        
        
   $prizeItem = DB::table('prizes')
    //->where('prize_status', 'No')
      ->where('prize_venue', $venue)
      ->count('prize'); 

    $prizeDistributed = DB::table('prizes')
      ->where('prize_venue', $venue)
      ->where('prize_status', 'Yes')
      ->count('prize');
      
     //Get Winner Data
       $winnersData = DB::table('winners')
      ->where('winners_venue', $venue)
      ->count();

   //Gift Distributed  Data
       $giftApproved = DB::table('registrations')
        ->where('venue', $venue)
        ->where('gift', 'Approved')
        ->count();
        
        //get Veuwise Country Data
        $countryCounts = DB::table('registrations')
                      ->where('venue', $venue)
                      ->select('country', DB::raw('COUNT(*) as count'))
                      ->groupBy('country')
                      ->orderBy('count', 'desc')
                      ->get();
      
    // Pass all data to the view
    return view('backend.pages.venue', [
        'data' => $venueData,
        'countryData' => $countryCount,
        'verifiedRegister' => $verifiedRegisterCount,
        'prizeItem' => $prizeItem,
        'prizeDistributed' => $prizeDistributed,
        'winnersData' => $winnersData,
        'giftApproved' => $giftApproved,
        'countryCounts' => $countryCounts,
    ]);
}

    // PDF: one event (venue)
    public function createPdfByVenue(Request $request, $venue){
        return \App\Support\RegistrationReport::download(['venue' => $venue]);
    }
    
    // PDF: one country at one event
    public function countryVenueWiseReport(Request $request, $country, $venue){
        return \App\Support\RegistrationReport::download(['country' => $country, 'venue' => $venue]);
    }
}
