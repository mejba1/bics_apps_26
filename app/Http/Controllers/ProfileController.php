<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use Illuminate\Http\Request;

/**
 * Public participant profile: /p/{code} (NFC card, QR code or link). Only public fields are shown.
 * ?s=qr / ?s=nfc tells where a visit came from (counted per day; nothing about the visitor is stored).
 */
class ProfileController extends Controller
{
    private const ACTIONS = ['share', 'copy', 'whatsapp', 'call', 'email', 'website', 'map', 'social'];

    public function show(Request $request, string $code){
        $p = $this->find($code);
        if ($p->status !== 'active') return response()->view('profiles.unavailable', ['p' => $p], 410);
        if ($this->counts($request, $p)) {
            $source = in_array($request->query('s'), ['qr', 'nfc'], true) ? $request->query('s') : 'link';
            $p->record('view_' . $source);
        }
        return view('profiles.show', ['p' => $p, 'events' => $p->isPublic('events') ? $p->events() : collect()]);
    }

    public function vcard(Request $request, string $code){
        $p = $this->activeOrFail($code);
        if ($this->counts($request, $p)) $p->record('vcard');
        return response($p->vcard(), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . \Illuminate\Support\Str::slug($p->name ?: $p->code) . '.vcf"',
        ]);
    }

    /** QR code of the profile: svg (vector), eps (vector, print) or png (?size=300…2000). */
    public function qr(Request $request, string $code, string $format){
        $p = $this->activeOrFail($code);
        if ($request->boolean('download')) $p->record('qr_download');
        $name = $p->code . '-qr.' . $format;
        $disposition = ($request->boolean('download') ? 'attachment' : 'inline') . '; filename="' . $name . '"';
        return match ($format) {
            'svg' => response($p->qrSvg(600), 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => $disposition]),
            'eps' => response($p->qrEps(600), 200, ['Content-Type' => 'application/postscript', 'Content-Disposition' => $disposition]),
            default => response($p->qrPng(max(300, min(2000, (int) $request->query('size', 1000)))), 200, ['Content-Type' => 'image/png', 'Content-Disposition' => $disposition, 'Cache-Control' => 'public, max-age=86400']),
        };
    }

    /** Buttons pressed on the profile (share, call…): counted only, as "initiated" (the platform cannot know if they completed). */
    public function track(Request $request, string $code){
        $p = $this->activeOrFail($code);
        $action = (string) $request->input('action');
        if (in_array($action, self::ACTIONS, true) && $this->counts($request, $p)) $p->record('click_' . $action);
        return response()->noContent();
    }

    private function find(string $code): Participant
    {
        abort_unless(preg_match('/^[A-Z]{2,10}-\d{6}$/', $code), 404);
        $p = Participant::where('code', $code)->first();
        if (!$p) {
            // a duplicate profile merged into another: its old link / QR code opens the right person
            $old = Participant::onlyTrashed()->where('code', $code)->whereNotNull('merged_into_id')->first();
            $to = $old ? Participant::find($old->merged_into_id) : null;
            if ($to) abort(redirect(str_replace('/' . $code, '/' . $to->code, request()->fullUrl()), 301));
        }
        abort_unless($p, 404);
        return $p;
    }

    /** Custom link name: /p/dr-nadia-karim shows the same profile as /p/MBR-000302. */
    public function slug(Request $request, string $slug){
        $p = Participant::where('slug', strtolower($slug))->first();
        abort_unless($p, 404);
        return $this->show($request, $p->code);
    }

    private function activeOrFail(string $code): Participant
    {
        $p = $this->find($code);
        abort_unless($p->status === 'active', 404);
        return $p;
    }

    /** Visits are counted, except from bots and from the participant themself (signed in to their dashboard). */
    private function counts(Request $request, Participant $p): bool
    {
        return !$this->isBot($request) && \Illuminate\Support\Facades\Auth::guard('participant')->id() !== $p->id;
    }

    private function isBot(Request $request): bool
    {
        return (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|curl|wget|headless|lighthouse/i', (string) $request->userAgent());
    }
}
