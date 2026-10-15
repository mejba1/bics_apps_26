<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Participant;
use App\Support\ParticipantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Participants (permission "participants.manage"; trash / restore needs "participants.delete").
 * Permanent participant profiles: list (search, filters, sorting, paging, bulk actions, CSV), create / edit with
 * per-field privacy, photo / logo, QR downloads and profile statistics. The code and URL never change.
 */
class ParticipantController extends Controller
{
    public function index(Request $request){
        // opens on the pinned event's participants; ?event=all shows everyone, ?event={id} another event
        $pinnedId = optional(\App\Support\PinnedEvent::event())->id;
        $ev = $request->query('event');
        $selected = $ev === 'all' ? '' : ((int) $ev ?: $pinnedId);
        return view('backend.pages.participants.index', [
            'pinnedId' => $pinnedId, 'selectedEvent' => $selected ? (string) $selected : '',
            'events' => Event::orderByDesc('is_current')->orderByDesc('event_start')->get(['id', 'event_short', 'venue_code']),
            'counts' => $this->counts($selected ? (int) $selected : null),
        ]);
    }

    /** JSON page of participants for the list. */
    public function data(Request $request){
        $q = $this->filtered($request);
        $sort = ['code' => 'sequence', 'name' => 'first_name', 'updated' => 'updated_at', 'organization' => 'organization'][$request->input('sort')] ?? 'sequence';
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $page = $q->orderBy($sort, $dir)->orderBy('id', $dir)->paginate(min(100, max(10, (int) $request->input('per', 25))));
        $views = DB::table('profile_stats')->whereIn('participant_id', $page->pluck('id'))->where('metric', 'like', 'view_%')
            ->select('participant_id', DB::raw('SUM(count) as n'))->groupBy('participant_id')->pluck('n', 'participant_id');
        $events = DB::table('registrations as r')->leftJoin('events as e', 'e.id', '=', 'r.event_id')->whereIn('r.participant_id', $page->pluck('id'))
            ->select('r.participant_id', DB::raw('COALESCE(e.event_short, r.venue) as ev'))->get()->groupBy('participant_id');
        return response()->json([
            'rows' => collect($page->items())->map(fn ($p) => [
                'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'initials' => $p->initials, 'status' => $p->status, 'trashed' => (bool) $p->deleted_at,
                'photo' => $p->photo && is_file(base_path($p->photo)) ? \App\Support\Thumb::url($p->photo, 480) : null,
                'designation' => $p->designation, 'organization' => $p->organization, 'country' => $p->country, 'email' => $p->email,
                'events' => ($events[$p->id] ?? collect())->pluck('ev')->filter()->unique()->values(),
                'views' => (int) ($views[$p->id] ?? 0), 'url' => $p->url(), 'edit' => route('participants.edit', $p->id), 'updated' => optional($p->updated_at)->format('j M Y'),
            ]),
            'total' => $page->total(), 'page' => $page->currentPage(), 'pages' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
            'counts' => $this->counts((int) $request->input('event') ?: null),
        ]);
    }

    public function create(){
        return view('backend.pages.participants.form', $this->formData(new Participant(['visibility' => null])));
    }

    public function store(Request $request){
        $data = $this->validated($request);
        $p = Participant::createWithCode($data, $request->user()->id);
        return response()->json(['success' => true, 'message' => 'Participant ' . $p->code . ' created. Profile: ' . $p->url(), 'redirect' => route('participants.edit', $p->id)]);
    }

    public function edit(int $participant){
        return view('backend.pages.participants.form', $this->formData(Participant::withTrashed()->findOrFail($participant)));
    }

    public function update(Request $request, int $participant){
        $p = Participant::withTrashed()->findOrFail($participant);
        $data = $this->validated($request, $p);
        $before = $p->only(array_keys($data));
        $p->fill($data)->save();
        \App\Support\ParticipantAccess::logChanges($p, $before, 'admin', $request->user()->id, $request->ip());
        return response()->json(['success' => true, 'message' => 'Saved. The profile URL and QR code stay the same.']);
    }

    /** Photo or organization logo (PNG / JPG / WEBP). */
    public function upload(Request $request){
        $request->validate(['image' => 'required|file|max:5120|mimes:png,jpg,jpeg,webp'], ['image.mimes' => 'Please upload a PNG, JPG or WEBP image.', 'image.max' => 'The image must be smaller than 5 MB.']);
        $file = $request->file('image');
        $ext = ['png' => 'png', 'webp' => 'webp'][strtolower($file->getClientOriginalExtension())] ?? 'jpg';
        $name = Str::lower(Str::random(16)) . '.' . $ext;
        $file->move(base_path('upload/participants'), $name);
        $path = 'upload/participants/' . $name;
        $size = @getimagesize(base_path($path));
        if ($size && max($size[0], $size[1]) > 1200) \App\Support\Thumb::shrink(base_path($path), base_path($path), 1200);
        return response()->json(['success' => true, 'path' => $path, 'url' => asset($path)]);
    }

    /** Link settings: which web address profile links, QR codes and NFC cards use (Super Admin). */
    public function linkSettings(Request $request){
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a Super Admin can change the link address.');
        $data = $request->validate(['mode' => 'required|in:auto,custom', 'base_url' => 'nullable|required_if:mode,custom|url|max:190'],
            ['base_url.required_if' => 'Enter the address, e.g. https://bics.org', 'base_url.url' => 'Enter the full address, e.g. https://bics.org']);
        if ($data['mode'] === 'custom' && !preg_match('#^https?://#i', (string) $data['base_url'])) throw ValidationException::withMessages(['base_url' => 'Start with https://']);
        \App\Support\PublicLinks::save($data['mode'], $data['base_url'] ?? '');
        $base = \App\Support\PublicLinks::base();
        return response()->json(['success' => true, 'base' => $base, 'local' => \App\Support\PublicLinks::isLocal($base),
            'message' => 'Links now use ' . $base . ' (e.g. ' . $base . '/p/MBR-000001).']);
    }

    /** Participant dashboard settings: sign-in on/off, editing on/off, fields locked for everyone. */
    public function dashboardSettings(Request $request){
        $data = $request->validate(['portal' => 'required|boolean', 'edit' => 'required|boolean', 'locked' => 'nullable|array', 'locked.*' => 'string']);
        \App\Support\ParticipantAccess::save((bool) $data['portal'], (bool) $data['edit'], $data['locked'] ?? []);
        return response()->json(['success' => true, 'message' => 'Participant dashboard settings saved.']);
    }

    /** Email a participant their sign-in link (e.g. when they ask the help desk). */
    public function sendLogin(Request $request, int $participant){
        $p = Participant::findOrFail($participant);
        $s = \App\Support\ParticipantAccess::settings();
        if (!$s['portal']) return response()->json(['message' => 'Participant sign-in is switched off (Dashboard settings).'], 422);
        if ($p->status !== 'active') return response()->json(['message' => 'This profile is suspended.'], 422);
        if (!$p->email) return response()->json(['message' => 'This participant has no email address.'], 422);
        if (!\App\Support\ParticipantAccess::sendLogin($p->email, $request->ip())) {
            return response()->json(['message' => 'Not sent: too many sign-in emails in the last 15 minutes, or the email could not be sent (see System > Email).'], 422);
        }
        return response()->json(['success' => true, 'message' => 'Sign-in link sent to ' . $p->email . '.']);
    }

    /** Bulk: activate / suspend / delete / restore. */
    public function bulk(Request $request){
        $data = $request->validate(['ids' => 'required|array|max:1000', 'ids.*' => 'integer', 'action' => 'required|in:activate,suspend,delete,restore']);
        if (in_array($data['action'], ['delete', 'restore'], true)) abort_unless($request->user()->can('participants.delete'), 403, 'You cannot delete or restore participants.');
        $q = Participant::withTrashed()->whereIn('id', $data['ids']);
        $n = match ($data['action']) {
            'activate' => $q->update(['status' => 'active', 'updated_at' => now()]),
            'suspend' => $q->update(['status' => 'suspended', 'updated_at' => now()]),
            'delete' => Participant::whereIn('id', $data['ids'])->get()->each->delete()->count(),
            'restore' => Participant::onlyTrashed()->whereIn('id', $data['ids'])->get()->each->restore()->count(),
        };
        $done = ['activate' => 'activated', 'suspend' => 'suspended (profiles hidden)', 'delete' => 'moved to the trash', 'restore' => 'restored'][$data['action']];
        return response()->json(['success' => true, 'message' => $n . ' ' . Str::plural('participant', $n) . ' ' . $done . '.']);
    }

    /** CSV of the filtered list (opens in Excel). */
    public function export(Request $request){
        $rows = $this->filtered($request)->orderBy('sequence')->get();
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Participant ID', 'Profile URL', 'Status', 'Title', 'First name', 'Last name', 'Designation', 'Organization', 'Department', 'Category', 'Country', 'Email', 'Mobile', 'Phone', 'WhatsApp', 'Website', 'Created']);
        foreach ($rows as $p) {
            fputcsv($out, [$p->code, $p->url(), $p->deleted_at ? 'deleted' : $p->status, $p->title, $p->first_name, $p->last_name, $p->designation, $p->organization,
                $p->department, $p->category, $p->country, $p->email, $p->mobile, $p->phone, $p->whatsapp, $p->website, optional($p->created_at)->format('Y-m-d')]);
        }
        rewind($out);
        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="participants-' . date('Ymd') . '.csv"']);
    }

    /** Status tiles (All / Active / Suspended / Trash) for one event's participants, or everyone. */
    private function counts(?int $eventId): array
    {
        $event = $eventId ? Event::find($eventId) : null;
        $scope = function ($q) use ($event) {
            if ($event) $q->whereIn('id', DB::table('registrations')->where(fn ($w) => $w->where('event_id', $event->id)->orWhere('venue', $event->venue_code))->whereNotNull('participant_id')->select('participant_id'));
            return $q;
        };
        return [
            'all' => $scope(Participant::query())->count(), 'active' => $scope(Participant::where('status', 'active'))->count(),
            'suspended' => $scope(Participant::where('status', 'suspended'))->count(), 'trashed' => $scope(Participant::onlyTrashed())->count(),
            'photo' => $scope(Participant::whereNotNull('photo'))->count(),
        ];
    }

    private function filtered(Request $request)
    {
        $status = $request->input('status');
        $q = $status === 'trashed' ? Participant::onlyTrashed() : Participant::query();
        if (in_array($status, ['active', 'suspended'], true)) $q->where('status', $status);
        if ($s = trim((string) $request->input('q'))) {
            $q->where(function ($w) use ($s) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
                $w->where('code', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhere(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'like', $like)
                    ->orWhere('email', 'like', $like)->orWhere('organization', 'like', $like)->orWhere('designation', 'like', $like)->orWhere('country', 'like', $like);
            });
        }
        if ($ev = (int) $request->input('event')) {
            $event = Event::find($ev);
            if ($event) $q->whereIn('id', DB::table('registrations')->where(fn ($w) => $w->where('event_id', $event->id)->orWhere('venue', $event->venue_code))->whereNotNull('participant_id')->select('participant_id'));
        }
        if ($request->input('photo') === 'yes') $q->whereNotNull('photo');
        if ($request->input('photo') === 'no') $q->whereNull('photo');
        return $q;
    }

    private function formData(Participant $p): array
    {
        $regs = $p->exists ? DB::table('registrations as r')->leftJoin('events as e', 'e.id', '=', 'r.event_id')->where('r.participant_id', $p->id)
            ->orderByDesc('r.created_at')->get(['r.id', 'r.qr_code', 'r.verify_status', 'r.created_at', 'r.venue', 'r.event_id', 'r.idcard_assigned_at', 'e.event_short']) : collect();
        // the event of each registration (older ones only have the venue) and its certificate
        $regs = $regs->map(function ($r) {
            $ev = $r->event_id ? Event::find($r->event_id) : Event::forVenue($r->venue);
            $r->event_id = $ev?->id;
            $r->event_short = $r->event_short ?: ($ev->event_short ?? null);
            $r->cert = $ev ? DB::table('certificates')->where('event_id', $ev->id)->where('registration_id', $r->id)->first(['number', 'assigned_at', 'emailed_at', 'token']) : null;
            return $r;
        });
        return [
            'p' => $p, 'fields' => Participant::FIELDS, 'registrations' => $regs,
            'stats' => $p->exists ? $p->stats() : [], 'canDelete' => auth()->user()->can('participants.delete'),
            'cards' => $p->exists ? \App\Models\NfcCard::where('participant_id', $p->id)->orderByDesc('id')->get() : collect(),
            'canNfc' => auth()->user()->can('nfc.manage'), 'canCert' => auth()->user()->can('certificates.manage'), 'canIdCard' => auth()->user()->can('idcards.assign'),
            'access' => \App\Support\ParticipantAccess::settings(), 'editableList' => \App\Support\ParticipantAccess::EDITABLE,
            'changes' => $p->exists ? DB::table('participant_changes as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')->where('c.participant_id', $p->id)
                ->orderByDesc('c.id')->limit(15)->get(['c.source', 'c.changes', 'c.created_at', 'u.name as user']) : collect(),
        ];
    }

    private function validated(Request $request, ?Participant $p = null): array
    {
        $data = $request->validate(ParticipantRules::rules() + [
            'status' => 'required|in:active,suspended', 'editing_enabled' => 'nullable|boolean',
            'locked_fields' => 'nullable|array', 'locked_fields.*' => 'string',
        ], ParticipantRules::messages());
        // admins may use any uploaded or registration image
        $data = ParticipantRules::normalize($data + ['socials' => null, 'custom_fields' => null, 'visibility' => null, 'slug' => null, 'email' => null], $p, fn () => true);
        $data['editing_enabled'] = (bool) ($data['editing_enabled'] ?? true);
        $data['locked_fields'] = array_values(array_intersect($data['locked_fields'] ?? [], array_keys(\App\Support\ParticipantAccess::EDITABLE)));
        return $data;
    }
}
