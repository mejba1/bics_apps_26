<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin > Certificates (permission "certificates.manage"), per event (?event=ID, default the current event):
 * design (live PDF preview), email settings, the participant list, downloads and sending (manual, in batches).
 */
class CertificateController extends Controller
{
    public function index(Request $request){
        $event = $this->event($request);
        return view('backend.pages.certificates', [
            'event' => $event,
            'events' => Event::orderByDesc('is_current')->orderByDesc('event_start')->orderByDesc('id')->get(['id', 'event_short', 'venue_code', 'is_current']),
            'design' => Certificate::design($event),
            'defaults' => Certificate::designDefaults($event),
            'email' => Certificate::email($event),
            'templates' => Certificate::TEMPLATES,
            'nameStyles' => Certificate::NAME_STYLES,
            'modes' => Certificate::EMAIL_MODES,
            'eligible' => Certificate::ELIGIBLE,
            'placeholders' => Certificate::PLACEHOLDERS,
        ]);
    }

    public function saveDesign(Request $request){
        $event = $this->event($request);
        Certificate::saveDesign($event, $this->designInput($request));
        return response()->json(['success' => true, 'message' => 'Certificate design saved for ' . $event->event_short . '.', 'design' => Certificate::design($event)]);
    }

    public function resetDesign(Request $request){
        $event = $this->event($request);
        Certificate::resetDesign($event);
        return response()->json(['success' => true, 'message' => 'Back to the default design.', 'design' => Certificate::design($event)]);
    }

    /** PDF of the design in the form (not saved yet), for a sample person or a chosen participant. */
    public function preview(Request $request){
        $event = $this->event($request);
        $design = Certificate::cleanDesign($event, $this->designInput($request));
        $r = $request->filled('registration_id') ? $this->person($event, (int) $request->input('registration_id')) : Certificate::sample();
        $cert = $r->id ? DB::table('certificates')->where('event_id', $event->id)->where('registration_id', $r->id)->first() : null;
        return response(Certificate::pdf($event, $r, $cert, $design)->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="certificate-preview.pdf"']);
    }

    /** Logo / signature / background image (PNG or JPG). */
    public function upload(Request $request){
        $request->validate(['image' => 'required|file|max:4096|mimes:png,jpg,jpeg'], ['image.mimes' => 'Please upload a PNG or JPG image.', 'image.max' => 'The image must be smaller than 4 MB.']);
        $file = $request->file('image');
        $ext = strtolower($file->getClientOriginalExtension()) === 'png' ? 'png' : 'jpg';
        $name = Str::lower(Str::random(16)) . '.' . $ext;
        $file->move(base_path('upload/certificates'), $name);
        $path = 'upload/certificates/' . $name;
        // Very large photos make slow PDFs: keep them reasonable
        $size = @getimagesize(base_path($path));
        if ($size && max($size[0], $size[1]) > 2400) \App\Support\Thumb::shrink(base_path($path), base_path($path), 2400);
        return response()->json(['success' => true, 'path' => $path, 'url' => asset($path)]);
    }

    public function saveEmail(Request $request){
        $event = $this->event($request);
        $data = $request->validate([
            'mode' => ['required', Rule::in(array_keys(Certificate::EMAIL_MODES))],
            'eligible' => ['required', Rule::in(array_keys(Certificate::ELIGIBLE))],
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:3000',
            'attach_pdf' => 'required|boolean',
        ]);
        Certificate::saveEmail($event, $data);
        return response()->json(['success' => true, 'message' => [
            'off' => 'Certificate emails are off.', 'auto' => 'Saved. People get their certificate by email as soon as they are checked in.',
            'manual' => 'Saved. Send certificates from the participant list.',
        ][$data['mode']], 'email' => Certificate::email($event)]);
    }

    /** The certificate email (texts in the form) to the logged-in admin, with a sample certificate. */
    public function testEmail(Request $request){
        $event = $this->event($request);
        $t = array_merge(Certificate::email($event), $request->only(['subject', 'body', 'attach_pdf']));
        $sample = Certificate::sample();
        $cert = (object) ['number' => Certificate::design($event)['number_prefix'] . '-000001', 'issued_at' => now(), 'token' => 'sample'];
        $vars = Certificate::vars($event, $sample, $cert);
        try {
            $html = view('mail.certificate', ['t' => $t, 'vars' => $vars, 'link' => config('bics.public_url') . '/certificate/sample', 'event' => $event])->render();
            $pdf = !empty($t['attach_pdf']) && $t['attach_pdf'] !== '0' ? Certificate::pdf($event, $sample, $cert)->output() : null;
            Mail::to($request->user()->email)->send(new \App\Mail\CertificateMail($html, '[Test] ' . \App\Support\EmailTemplate::text($t['subject'], $vars), $pdf, 'sample-certificate.pdf'));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Not sent: ' . Str::limit($e->getMessage(), 200) . ' Check Admin > Email Server.'], 500);
        }
        return response()->json(['success' => true, 'message' => 'Test email sent to ' . $request->user()->email . '.']);
    }

    /** Everyone registered for the event, with their certificate status. */
    public function participants(Request $request){
        $event = $this->event($request);
        $certs = DB::table('certificates')->where('event_id', $event->id)->get()->keyBy('registration_id');
        $rows = Certificate::people($event)->orderBy('r.firstname')->orderBy('r.lastname')
            ->get(['r.id', 'r.title', 'r.firstname', 'r.lastname', 'r.email', 'r.country', 'r.profession', 'r.qr_code', 'r.verify_status'])
            ->map(function ($r) use ($certs, $event) {
                $c = $certs[$r->id] ?? null;
                return [
                    'id' => $r->id, 'name' => trim(($r->title ? $r->title . ' ' : '') . $r->firstname . ' ' . $r->lastname), 'email' => $r->email,
                    'country' => $r->country, 'profession' => $r->profession, 'pin' => $r->qr_code, 'checked_in' => $r->verify_status === 'Yes',
                    'eligible' => Certificate::isEligible($event, $r), 'valid_email' => (bool) filter_var($r->email, FILTER_VALIDATE_EMAIL),
                    'number' => $c->number ?? null, 'emailed_at' => $c && $c->emailed_at ? substr($c->emailed_at, 0, 16) : null,
                    'error' => $c->email_error ?? null, 'downloads' => (int) ($c->downloads ?? 0),
                    'link' => $c ? url('/certificate/' . $c->token) : null,
                    'assigned_at' => $c && $c->assigned_at ? substr($c->assigned_at, 0, 16) : null,
                ];
            })->values();
        return response()->json(['participants' => $rows, 'email' => Certificate::email($event)]);
    }

    /** Download one person's certificate (issues it if needed). */
    public function download(Request $request, int $registration){
        $event = $this->event($request);
        $r = $this->person($event, $registration);
        $cert = Certificate::issue($event, $r);
        return Certificate::pdf($event, $r, $cert)->download(Certificate::fileName($r, $cert));
    }

    /** Email certificates to up to 20 people per request (the page sends the list in batches and shows progress). */
    public function send(Request $request){
        $event = $this->event($request);
        $ids = $request->validate(['ids' => 'required|array|max:20', 'ids.*' => 'integer'])['ids'];
        @set_time_limit(300);
        $results = [];
        foreach ($ids as $id) {
            $r = DB::table('registrations')->where('id', $id)->first();
            if (!$r || !Certificate::people($event)->where('r.id', $id)->exists()) { $results[$id] = 'Not registered for this event.'; continue; }
            $results[$id] = Certificate::send($event, $id);
        }
        $failed = array_filter($results);
        return response()->json(['success' => true, 'sent' => count($results) - count($failed), 'failed' => $failed]);
    }

    /**
     * Send to one specific participant, chosen by the admin: works whatever their check-in status or the
     * sending mode (those only govern automatic and bulk sending). Optionally to another email address.
     */
    public function sendOne(Request $request){
        $event = $this->event($request);
        $data = $request->validate([
            'registration_id' => 'required|integer',
            'to' => 'nullable|email|max:190',
        ], ['to.email' => 'Please enter a valid email address.']);
        $r = $this->person($event, (int) $data['registration_id']);
        $to = trim((string) ($data['to'] ?? '')) ?: null;
        $error = Certificate::send($event, $r->id, $to);
        if ($error) return response()->json(['message' => 'Not sent: ' . $error], 500);
        $cert = DB::table('certificates')->where('event_id', $event->id)->where('registration_id', $r->id)->first();
        return response()->json(['success' => true, 'message' => 'Certificate ' . $cert->number . ' sent to ' . ($to ?: $r->email) . '.']);
    }

    /**
     * Assign certificates to participants (shown on their dashboard under this event), or remove them.
     * Works for anyone registered for the event, whatever their check-in status — the admin decides.
     */
    public function assign(Request $request){
        $event = $this->event($request);
        $data = $request->validate(['ids' => 'required|array|min:1|max:2000', 'ids.*' => 'integer', 'assign' => 'required|boolean'],
            ['ids.required' => 'Choose at least one participant.']);
        $people = Certificate::people($event)->whereIn('r.id', $data['ids'])->get(['r.*']);
        if ($people->isEmpty()) return response()->json(['message' => 'None of these people are registered for ' . $event->event_short . '.'], 422);
        $n = 0;
        foreach ($people as $r) {
            if ($data['assign']) { Certificate::assign($event, $r, $request->user()->id); $n++; }
            elseif (Certificate::unassign($event, $r->id)) $n++;
        }
        $linked = $people->whereNotNull('participant_id')->count();
        return response()->json(['success' => true, 'count' => $n, 'message' => $data['assign']
            ? $n . ' certificate' . ($n === 1 ? '' : 's') . ' assigned for ' . $event->event_short . '.' . ($linked < $people->count() ? ' ' . ($people->count() - $linked) . ' of them have no participant profile yet, so they see it only by email or link.' : ' Participants see them in their dashboard.')
            : $n . ' certificate' . ($n === 1 ? '' : 's') . ' removed from participant dashboards.']);
    }

    private function designInput(Request $request): array
    {
        $d = $request->input('design', []);
        return is_array($d) ? $d : (json_decode((string) $d, true) ?: []);
    }

    private function person(Event $event, int $id): object
    {
        $r = Certificate::people($event)->where('r.id', $id)->first(['r.*']);
        abort_unless($r, 404, 'This person is not registered for this event.');
        return $r;
    }

    private function event(Request $request): Event
    {
        $id = (int) $request->query('event', $request->input('event_id', 0));
        $event = ($id ? Event::find($id) : null) ?? \App\Support\PinnedEvent::event();   // opens on the pinned event
        abort_unless($event, 422, 'Create an event first (Admin > Events).');
        return $event;
    }
}
