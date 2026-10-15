<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\NfcCard;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin > NFC Cards (permission "nfc.manage"): inventory, assignment, encoding (Web NFC on Android Chrome, or an
 * external encoder), verification, activation, deactivation, replacement, reassignment, print status and history.
 * The model (App\Models\NfcCard) holds the rules; this controller only routes requests to it.
 */
class NfcCardController extends Controller
{
    public function index(Request $request){
        // opens on the pinned event's cards; ?event=all shows every card, ?event={id} another event
        $pinnedId = optional(\App\Support\PinnedEvent::event())->id;
        $ev = $request->query('event');
        $selected = $ev === 'all' ? null : ((int) $ev ?: $pinnedId);
        return view('backend.pages.nfc.index', [
            'pinnedId' => $pinnedId, 'selectedEvent' => $selected ? (string) $selected : '',
            'counts' => $this->counts($selected),
            'events' => Event::orderByDesc('is_current')->orderByDesc('event_start')->get(['id', 'event_short', 'venue_code']),
        ]);
    }

    public function data(Request $request){
        $q = NfcCard::query()->with('participant:id,code,title,first_name,last_name,organization,photo,status');
        $f = $request->input('filter');
        $eventId = (int) $request->input('event') ?: null;
        if ($f !== 'available') $this->forEvent($q, $eventId);   // blank cards in stock belong to no event
        if (isset(NfcCard::STATUS[$f])) $q->where('status', $f);
        if ($f === 'to_encode') $q->whereIn('status', NfcCard::IN_USE)->where('encode_status', '!=', 'encoded');
        if ($f === 'to_verify') $q->where('encode_status', 'encoded')->where('verify_status', '!=', 'verified')->whereIn('status', NfcCard::IN_USE);
        if ($f === 'to_activate') $q->where('status', 'assigned')->where('encode_status', 'encoded');
        if ($f === 'off') $q->whereIn('status', ['lost', 'damaged', 'deactivated']);
        if ($f === 'to_print') $q->whereIn('status', NfcCard::IN_USE)->where('print_status', 'not_printed');
        if ($s = trim((string) $request->input('q'))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
            $q->where(fn ($w) => $w->where('card_no', 'like', $like)->orWhere('uid', 'like', '%' . str_replace(':', '', strtoupper($s)) . '%')->orWhere('uid', 'like', $like)
                ->orWhereHas('participant', fn ($p) => $p->where('code', 'like', $like)->orWhere(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'like', $like)->orWhere('organization', 'like', $like)));
        }
        $page = $q->orderByDesc('updated_at')->orderByDesc('id')->paginate(min(100, max(10, (int) $request->input('per', 25))));
        return response()->json([
            'rows' => collect($page->items())->map(fn ($c) => $this->row($c)),
            'total' => $page->total(), 'page' => $page->currentPage(), 'pages' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
            'counts' => $this->counts($eventId),
        ]);
    }

    /** Only cards of participants registered for the event (no event = every card). */
    private function forEvent($q, ?int $eventId)
    {
        $event = $eventId ? Event::find($eventId) : null;
        if ($event) $q->whereIn('participant_id', \App\Support\PinnedEvent::registrations($event)->whereNotNull('participant_id')->select('participant_id'));
        return $q;
    }

    public function stock(Request $request){
        $data = $request->validate(['count' => 'nullable|integer|min:0|max:2000', 'uids' => 'nullable|string|max:100000']);
        $uids = preg_split('/[\s,;]+/', (string) ($data['uids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        if (!($data['count'] ?? 0) && !$uids) throw ValidationException::withMessages(['count' => 'Enter how many cards to add, or paste their UIDs.']);
        $made = NfcCard::addToStock((int) ($data['count'] ?? 0), $uids, $request->user()->id);
        return response()->json(['success' => true, 'message' => count($made) . ' cards added to stock (' . $made[0]->card_no . ' – ' . end($made)->card_no . ').']);
    }

    /** Give a card from stock to every active participant of an event who has no card in use yet. */
    public function assignEvent(Request $request){
        $event = Event::findOrFail($request->validate(['event_id' => 'required|integer'])['event_id']);
        $ids = DB::table('registrations')->where(fn ($w) => $w->where('event_id', $event->id)->orWhere('venue', $event->venue_code))
            ->whereNotNull('participant_id')->distinct()->pluck('participant_id');
        $people = Participant::whereIn('id', $ids)->where('status', 'active')
            ->whereNotIn('id', NfcCard::whereIn('status', NfcCard::IN_USE)->whereNotNull('participant_id')->select('participant_id'))->orderBy('sequence')->get();
        @set_time_limit(300);
        $n = 0;
        DB::transaction(function () use ($people, $request, &$n) {
            foreach ($people as $p) { NfcCard::takeFromStock($request->user()->id)->assignTo($p, $request->user()->id); $n++; }
        });
        return response()->json(['success' => true, 'message' => $n ? $n . ' participants of ' . $event->event_short . ' got a card (taken from stock, new cards added when stock ran out).' : 'Everyone in ' . $event->event_short . ' already has a card.']);
    }

    public function show(NfcCard $card){
        $card->load('participant', 'logs.user:id,name', 'logs.participant:id,code');
        return view('backend.pages.nfc.show', [
            'card' => $card, 'row' => $this->row($card),
            'replaces' => $card->replaces_card_id ? NfcCard::find($card->replaces_card_id) : null,
            'replacedBy' => NfcCard::where('replaces_card_id', $card->id)->first(),
            'stock' => NfcCard::where('status', 'available')->whereNull('participant_id')->count(),
        ]);
    }

    /** One step of the workflow on a card. */
    public function action(Request $request, NfcCard $card, string $action){
        $uid = $request->user()->id;
        $msg = match ($action) {
            'assign' => (function () use ($request, $card, $uid) {
                $p = Participant::findOrFail($request->validate(['participant_id' => 'required|integer'])['participant_id']);
                $card->assignTo($p, $uid);
                return 'Card assigned to ' . $p->name . '. Next: write the link to the chip.';
            })(),
            'unassign' => tap('Card is back in stock.', fn () => $card->unassign($uid)),
            'encoded' => (function () use ($request, $card, $uid) {
                $d = $request->validate(['uid' => 'nullable|string|max:40', 'locked' => 'nullable|boolean', 'method' => 'nullable|in:web_nfc,external']);
                $card->markEncoded($d['uid'] ?? null, (bool) ($d['locked'] ?? false), ($d['method'] ?? 'external') === 'web_nfc' ? 'this phone, Web NFC' : 'external encoder', $uid);
                return 'Encoded. Next: verify by reading the card back.';
            })(),
            'encode_failed' => tap('Recorded.', fn () => $card->markEncodeFailed((string) $request->input('error', 'Unknown error'), $uid)),
            'verify' => (function () use ($request, $card, $uid) {
                $d = $request->validate(['read_url' => 'nullable|string|max:500', 'uid' => 'nullable|string|max:40', 'manual' => 'nullable|boolean']);
                $ok = $card->verify(!empty($d['manual']) ? null : (string) ($d['read_url'] ?? ''), $d['uid'] ?? null, $uid);
                if (!$ok) throw ValidationException::withMessages(['card' => 'The chip does not contain this card\'s link. Encode it again.']);
                return 'Verified: the chip opens the right link.';
            })(),
            'activate' => tap('Card is active: tapping it opens the profile.', fn () => $card->activate($uid)),
            'deactivate' => (function () use ($request, $card, $uid) {
                $d = $request->validate(['reason' => 'required|in:lost,damaged,deactivated', 'note' => 'nullable|string|max:300']);
                $card->deactivate($d['reason'], $d['note'] ?? null, $uid);
                return 'Card switched off. Tapping it now shows "no longer active".';
            })(),
            'replace' => (function () use ($request, $card, $uid) {
                $d = $request->validate(['reason' => 'required|in:lost,damaged,deactivated', 'new_card_id' => 'nullable|integer']);
                $new = !empty($d['new_card_id']) ? NfcCard::findOrFail($d['new_card_id']) : null;
                $new = $card->replace($d['reason'], $new, $uid);
                return 'Replacement card ' . $new->card_no . ' assigned. Encode and activate it.|' . route('nfc.show', $new->id);
            })(),
            'printed' => tap('Print status saved.', fn () => $card->setPrinted($request->boolean('printed'), $uid)),
            'note' => (function () use ($request, $card, $uid) {
                $note = trim((string) $request->validate(['note' => 'required|string|max:500'])['note']);
                $card->fill(['notes' => $note])->save();
                $card->log('note', $note, $uid);
                return 'Note saved.';
            })(),
            default => abort(404),
        };
        [$message, $redirect] = array_pad(explode('|', $msg, 2), 2, null);
        $card->refresh()->load('participant');
        return response()->json(['success' => true, 'message' => $message, 'card' => $this->row($card), 'redirect' => $redirect]);
    }

    /** Bulk: mark printed / not printed. */
    public function bulk(Request $request){
        $d = $request->validate(['ids' => 'required|array|max:2000', 'ids.*' => 'integer', 'action' => 'required|in:printed,unprinted']);
        $n = 0;
        foreach (NfcCard::whereIn('id', $d['ids'])->get() as $c) { $c->setPrinted($d['action'] === 'printed', $request->user()->id); $n++; }
        return response()->json(['success' => true, 'message' => $n . ' cards marked ' . ($d['action'] === 'printed' ? 'printed' : 'not printed') . '.']);
    }

    /** Participant search for "Assign" (and the encoding station). */
    public function participants(Request $request){
        $s = trim((string) $request->input('q'));
        if (mb_strlen($s) < 2) return response()->json([]);
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
        $cards = NfcCard::whereIn('status', NfcCard::IN_USE)->whereNotNull('participant_id')->get(['id', 'participant_id', 'card_no', 'status'])->keyBy('participant_id');
        return response()->json(Participant::where('status', 'active')
            ->where(fn ($w) => $w->where('code', 'like', $like)->orWhere(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'like', $like)->orWhere('email', 'like', $like)->orWhere('organization', 'like', $like))
            ->orderBy('first_name')->limit(15)->get()->map(fn ($p) => [
                'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'organization' => $p->organization,
                'card' => isset($cards[$p->id]) ? ['id' => $cards[$p->id]->id, 'card_no' => $cards[$p->id]->card_no, 'status' => $cards[$p->id]->status] : null,
            ]));
    }

    /** Encoding station (phone-friendly): find the participant → write → verify → activate → next. */
    public function station(){
        return view('backend.pages.nfc.station', ['counts' => $this->counts()]);
    }

    /** The card to encode for a participant: their card in use, or a new one from stock. */
    public function forParticipant(Request $request, Participant $participant){
        $card = NfcCard::where('participant_id', $participant->id)->whereIn('status', NfcCard::IN_USE)->first()
            ?? NfcCard::takeFromStock($request->user()->id)->assignTo($participant, $request->user()->id);
        return response()->json(['card' => $this->row($card->load('participant'))]);
    }

    public function export(Request $request){
        $rows = $this->forEvent(NfcCard::with('participant'), (int) $request->query('event') ?: null)->orderBy('sequence')->get();
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Card', 'UID', 'Status', 'Encoded', 'Verified', 'Printed', 'Participant ID', 'Participant', 'Organization', 'Link on chip', 'Encoded at', 'Activated at', 'Replaces']);
        $byId = $rows->keyBy('id');
        foreach ($rows as $c) {
            fputcsv($out, [$c->card_no, $c->uid, NfcCard::STATUS[$c->status][0], NfcCard::ENCODE[$c->encode_status][0], NfcCard::VERIFY[$c->verify_status][0], NfcCard::PRINT[$c->print_status][0],
                $c->participant->code ?? '', $c->participant->name ?? '', $c->participant->organization ?? '', $c->url(),
                optional($c->encoded_at)->format('Y-m-d H:i'), optional($c->activated_at)->format('Y-m-d H:i'), $c->replaces_card_id ? ($byId[$c->replaces_card_id]->card_no ?? '') : '']);
        }
        rewind($out);
        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="nfc-cards-' . date('Ymd') . '.csv"']);
    }

    private function row(NfcCard $c): array
    {
        $p = $c->participant;
        return [
            'id' => $c->id, 'card_no' => $c->card_no, 'uid' => $c->uid, 'url' => $c->url(), 'status' => $c->status, 'encode_status' => $c->encode_status,
            'verify_status' => $c->verify_status, 'print_status' => $c->print_status, 'locked' => $c->locked,
            'participant' => $p ? ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'organization' => $p->organization, 'profile' => $p->url(), 'edit' => route('participants.edit', $p->id),
                'photo' => $p->photo && is_file(base_path($p->photo)) ? \App\Support\Thumb::url($p->photo, 480) : null, 'initials' => $p->initials] : null,
            'encoded_at' => optional($c->encoded_at)->format('j M Y, H:i'), 'activated_at' => optional($c->activated_at)->format('j M Y, H:i'),
            'show' => route('nfc.show', $c->id), 'updated' => optional($c->updated_at)->format('j M Y'),
        ];
    }

    private function counts(?int $eventId = null): array
    {
        $scoped = fn () => $this->forEvent(NfcCard::query(), $eventId);
        $by = $scoped()->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');
        $inUse = fn () => $scoped()->whereIn('status', NfcCard::IN_USE);
        return [
            // stock is shared by every event
            'total' => (int) $by->sum(), 'available' => NfcCard::where('status', 'available')->count(), 'active' => (int) ($by['active'] ?? 0),
            'to_encode' => $inUse()->where('encode_status', '!=', 'encoded')->count(),
            'to_verify' => $inUse()->where('encode_status', 'encoded')->where('verify_status', '!=', 'verified')->count(),
            'to_activate' => $scoped()->where('status', 'assigned')->where('encode_status', 'encoded')->count(),
            'to_print' => $inUse()->where('print_status', 'not_printed')->count(),
            'off' => (int) (($by['lost'] ?? 0) + ($by['damaged'] ?? 0) + ($by['deactivated'] ?? 0)),
        ];
    }
}
