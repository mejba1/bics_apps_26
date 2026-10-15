<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Raffle;
use App\Support\WinnerEmail;
use App\Support\RaffleEntries;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Raffle Draw > Raffle setup (permission "raffle.prizes"), for the current event:
 * prizes and their draw order, who can win, and the hosts allowed to run the draw.
 * Assigning hosts and undoing a winner are Super Admin only. AJAX.
 */
class RaffleSetupController extends Controller
{
    public function __construct()
    {
        // the raffle of an ended / archived event is read-only (winner emails can still be sent)
        $this->middleware(function ($request, $next) {
            \App\Support\EventLock::ensureOpen($this->selected($request), 'raffle');
            return $next($request);
        })->except(['index', 'entries', 'entriesTemplate', 'email', 'emailPreview', 'emailTest', 'emailWinner']);
    }

    public function index(Request $request){
        $event = $this->selected($request);
        return view('backend.pages.raffle-setup', [
            'event' => $event,
            'current' => Raffle::event(),
            'events' => \App\Models\Event::orderByDesc('is_current')->orderByDesc('event_start')->orderByDesc('id')->get(['id', 'event_short', 'event_name', 'venue_code', 'status', 'is_current']),
            'data' => $this->data($request),
            'users' => User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'eligibilityOptions' => Raffle::ELIGIBILITY,
            'orderOptions' => Raffle::ORDERS,
            'shuffleOptions' => Raffle::SHUFFLES,
            'emailModes' => WinnerEmail::MODES,
            'emailPlaceholders' => WinnerEmail::PLACEHOLDERS,
            'organiser' => WinnerEmail::organiser(),
            'isSuper' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function storePrize(Request $request){
        $event = $this->event();
        $data = $this->validatePrize($request);
        DB::table('prizes')->insert([
            'event_id' => $event->id, 'prize_venue' => $event->venue_code, 'prize' => $data['prize'], 'prize_type' => $data['prize_type'],
            'prize_status' => 'No', 'sort' => (int) DB::table('prizes')->where('event_id', $event->id)->max('sort') + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Raffle::applyOrder($event);
        return $this->ok('Prize added.', $request);
    }

    public function updatePrize(Request $request, int $prize){
        $row = $this->prize($prize);
        $data = $this->validatePrize($request);
        DB::table('prizes')->where('id', $row->id)->update($data + ['updated_at' => now()]);
        if ($row->winner_id) DB::table('winners')->where('id', $row->winner_id)->update(['prize' => $data['prize'], 'prize_type' => $data['prize_type']]);
        Raffle::applyOrder($this->event());
        return $this->ok('Prize saved.', $request);
    }

    public function destroyPrize(Request $request, int $prize){
        $row = $this->prize($prize);
        if ($row->winner_id) $this->fail('prize', 'This prize has a winner. Undo the winner first.');
        DB::table('prizes')->where('id', $row->id)->delete();
        return $this->ok('Prize removed.', $request);
    }

    /** New draw order by hand: ids in order (first = drawn first). Switches the event to "Custom order". */
    public function order(Request $request){
        $event = $this->event();
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        foreach (array_values($ids) as $i => $id) {
            DB::table('prizes')->where('id', $id)->where('event_id', $event->id)->update(['sort' => $i + 1]);
        }
        $switched = Raffle::order($event) !== 'custom';
        if ($switched) Raffle::setOrder($event, 'custom');
        return $this->ok($switched ? 'Draw order saved. Order set to "Custom".' : 'Draw order saved.', $request);
    }

    /** "My list" of numbers that can win, with the name each number belongs to. */
    public function entries(Request $request){
        return response()->json(['entries' => RaffleEntries::list($this->event()), 'counts' => RaffleEntries::counts($this->event())]);
    }

    /** Add numbers typed one by one or pasted (any separator). */
    public function addEntries(Request $request){
        $event = $this->event();
        $text = $request->validate(['numbers' => 'required|string|max:200000'], ['numbers.required' => 'Type or paste at least one registration number.'])['numbers'];
        $numbers = RaffleEntries::fromText($text);
        if (!$numbers) $this->fail('numbers', 'No registration numbers found. Numbers have 4–10 digits, e.g. 716124.');
        return $this->entriesResult(RaffleEntries::add($event, $numbers, count($numbers) > 1 ? 'paste' : 'manual', $request->user()->id), $request);
    }

    /** Import numbers from Excel (.xlsx) or CSV. */
    public function importEntries(Request $request){
        $event = $this->event();
        $request->validate(['file' => 'required|file|max:5120|mimes:xlsx,csv,txt'], [
            'file.mimes' => 'Please upload an Excel .xlsx or a .csv file. (Old .xls files: open them in Excel and "Save as" .xlsx.)',
            'file.max' => 'The file must be smaller than 5 MB.',
        ]);
        try {
            $numbers = RaffleEntries::fromFile($request->file('file'));
        } catch (\RuntimeException $e) {
            $this->fail('file', $e->getMessage());
        }
        if (!$numbers) $this->fail('file', 'No registration numbers found in this file. Put them in one column (a header such as "Registration number" is fine).');
        return $this->entriesResult(RaffleEntries::add($event, $numbers, 'import', $request->user()->id), $request, count($numbers));
    }

    public function deleteEntry(Request $request, int $entry){
        DB::table('raffle_entries')->where('id', $entry)->where('event_id', $this->event()->id)->delete();
        return $this->ok('Number removed from the list.', $request);
    }

    public function clearEntries(Request $request){
        $n = DB::table('raffle_entries')->where('event_id', $this->event()->id)->delete();
        return $this->ok($n . ' ' . \Illuminate\Support\Str::plural('number', $n) . ' removed. The list is empty.', $request);
    }

    /** A ready-to-fill CSV (opens in Excel). */
    public function entriesTemplate(){
        $csv = "\xEF\xBB\xBFRegistration number,Name (optional)\n"; // headers only: sample numbers could be imported by mistake
        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="raffle-numbers-template.csv"']);
    }

    private function entriesResult(array $r, Request $request, ?int $found = null)
    {
        [$added, $already, $unknown] = $r;
        $parts = [($found !== null ? $found . ' found in the file, ' : '') . $added . ' added'];
        if ($already) $parts[] = $already . ' already on the list';
        if ($unknown) $parts[] = $unknown . ' not registered for this event (they can still win, by number)';
        return $this->ok(implode(' · ', $parts) . '.', $request);
    }

    /** Winner email settings (mode, texts, copy to organiser). */
    public function email(Request $request){
        $event = $this->event();
        $data = $this->validateEmail($request);
        WinnerEmail::save($event, $data);
        return $this->ok(['off' => 'Winner emails are off.', 'auto' => 'Saved. Winners get an email as soon as the host confirms them.', 'manual' => 'Saved. Send each winner their email from the prize list.'][$data['mode']], $request);
    }

    /** The email with the texts in the form (not saved yet), for a sample winner. */
    public function emailPreview(Request $request){
        $settings = array_merge(WinnerEmail::settings($this->event()), array_map(fn ($v) => (string) $v, $request->only(array_keys(WinnerEmail::DEFAULTS))));
        [$person, $prize, $title] = $this->sample();
        return response(WinnerEmail::render($settings, WinnerEmail::vars($person, $prize, $title, $this->event()), $prize, $title));
    }

    /** Send the email with the texts in the form to the logged-in admin. */
    public function emailTest(Request $request){
        $settings = array_merge(WinnerEmail::settings($this->event()), array_map(fn ($v) => (string) $v, $this->validateEmail($request)));
        $to = $request->user()->email;
        [$person, $prize, $title] = $this->sample();
        $vars = WinnerEmail::vars($person, $prize, $title, $this->event());
        try {
            Mail::to($to)->send(new \App\Mail\WinnerMail(WinnerEmail::render($settings, $vars, $prize, $title), '[Test] ' . \App\Support\EmailTemplate::text($settings['subject'], $vars)));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'The test email could not be sent: ' . \Illuminate\Support\Str::limit($e->getMessage(), 200) . ' Check Admin > Email Server.'], 500);
        }
        return response()->json(['success' => true, 'message' => 'Test email sent to ' . $to . '.']);
    }

    /** Send (or send again) the winner email of one prize. */
    public function emailWinner(Request $request, int $prize){
        $row = $this->prize($prize);
        if (!$row->winner_id) $this->fail('prize', 'This prize has no winner yet.');
        $error = WinnerEmail::send($row->winner_id);
        if ($error) return response()->json(['message' => 'Not sent: ' . $error, 'data' => $this->data($request)], 500);
        return $this->ok('Email sent to the winner.', $request);
    }

    private function validateEmail(Request $request): array
    {
        return $request->validate([
            'mode' => ['required', Rule::in(array_keys(WinnerEmail::MODES))],
            'subject' => 'required|string|max:200',
            'heading' => 'nullable|string|max:200',
            'intro' => 'nullable|string|max:2000',
            'collect' => 'nullable|string|max:2000',
            'closing' => 'nullable|string|max:1000',
            'bcc_organiser' => 'nullable|boolean',
        ]);
    }

    /** A sample winner for preview / test: the latest real winner of this event, or a made-up delegate. */
    private function sample(): array
    {
        $event = $this->event();
        $p = Raffle::prizes($event)->first();
        $w = DB::table('winners')->where('event_id', $event->id)->latest('id')->first();
        $r = $w && $w->register_id ? DB::table('registrations')->where('id', $w->register_id)->first() : null;
        $person = $r ?: (object) ['title' => 'Dr', 'firstname' => 'Sarah', 'lastname' => 'Rahman', 'qr_code' => '484704', 'profession' => 'Medical Doctor'];
        return [$person, $w->prize ?? ($p->prize ?? 'iPad Pro 11'), $w->prize_type ?? ($p->prize_type ?? 'First Prize')];
    }

    /** Draw screen: prize list on/off, countdown seconds, what the reels show while drawing. */
    public function screen(Request $request){
        $event = $this->event();
        $sounds = array_keys(Raffle::soundOptions());
        $data = $request->validate([
            'show_prizes' => 'required|boolean',
            'countdown' => 'required|integer|min:0|max:60',
            'shuffle' => ['required', Rule::in(array_keys(Raffle::SHUFFLES))],
            'draw_sound' => ['required', Rule::in($sounds)],
            'draw_volume' => 'required|integer|min:0|max:100',
            'draw_loop' => 'required|boolean',
            'tick' => 'required|boolean',
            'tick_volume' => 'required|integer|min:0|max:100',
            'win_sound' => ['required', Rule::in($sounds)],
            'win_volume' => 'required|integer|min:0|max:100',
        ], ['countdown.max' => 'The countdown can be at most 60 seconds.', 'draw_sound.in' => 'Choose a drawing sound from the list.', 'win_sound.in' => 'Choose a winner sound from the list.']);
        Raffle::setScreen($event, $data);
        return $this->ok('Draw screen saved. An open draw screen updates within a few seconds.', $request);
    }

    /** Upload a sound to the library (shared by all events): MP3 / WAV / OGG / M4A, up to 8 MB. */
    public function uploadSound(Request $request){
        $request->validate([
            'sound' => 'required|file|max:8192|mimes:mp3,wav,ogg,oga,m4a,mpga',
            'name' => 'nullable|string|max:60',
        ], ['sound.mimes' => 'Please upload an MP3, WAV, OGG or M4A file.', 'sound.max' => 'The sound must be smaller than 8 MB.']);
        $file = $request->file('sound');
        $ext = strtolower($file->getClientOriginalExtension());
        $ext = in_array($ext, ['mp3', 'wav', 'ogg', 'oga', 'm4a'], true) ? $ext : 'mp3';
        $name = trim((string) $request->input('name')) ?: \Illuminate\Support\Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 60, '');
        $id = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10));
        $stored = $id . '.' . $ext; // safe name: never the uploaded file name
        $file->move(base_path('upload/raffle-sounds'), $stored);
        $list = Raffle::uploadedSounds();
        $list[] = ['id' => $id, 'name' => $name, 'file' => $stored];
        Raffle::saveUploadedSounds($list);
        return $this->ok('Sound "' . $name . '" added. Choose it as the drawing or winner sound, then save.', $request) ;
    }

    public function deleteSound(Request $request, string $sound){
        $list = Raffle::uploadedSounds();
        $keep = [];
        foreach ($list as $s) {
            if ($s['id'] === $sound) { @unlink(base_path('upload/raffle-sounds/' . $s['file'])); continue; }
            $keep[] = $s;
        }
        Raffle::saveUploadedSounds($keep);
        return $this->ok('Sound removed. Events that used it fall back to the default sound.', $request);
    }

    /** Draw order mode: last prize first, first prize first, or custom. */
    public function orderMode(Request $request){
        $event = $this->event();
        $mode = $request->validate(['mode' => ['required', Rule::in(array_keys(Raffle::ORDERS))]])['mode'];
        Raffle::setOrder($event, $mode);
        return $this->ok(['last_first' => 'Last prize is drawn first, grand prize last.', 'first_first' => 'First prize is drawn first.', 'custom' => 'Custom order: use the arrows.'][$mode], $request);
    }

    public function hosts(Request $request){
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a Super Admin can assign raffle hosts.');
        $event = $this->event();
        $ids = $request->validate(['user_ids' => 'present|array', 'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('status', 'active')]])['user_ids'];
        Raffle::setHosts($event, $ids);
        return $this->ok(count($ids) ? 'Raffle hosts saved.' : 'No hosts assigned: nobody can start the draw.', $request);
    }

    public function eligibility(Request $request){
        $event = $this->event();
        $rule = $request->validate(['rule' => ['required', Rule::in(array_keys(Raffle::ELIGIBILITY))]])['rule'];
        Raffle::setEligibility($event, $rule);
        return $this->ok('Saved: ' . Raffle::ELIGIBILITY[$rule] . '.', $request);
    }

    /** Remove a confirmed winner (mistake on stage): the prize can be drawn again. */
    public function undoWinner(Request $request, int $prize){
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a Super Admin can undo a winner.');
        $row = $this->prize($prize);
        if (!$row->winner_id) $this->fail('prize', 'This prize has no winner.');
        DB::transaction(function () use ($row) {
            $winner = DB::table('winners')->where('id', $row->winner_id)->first();
            DB::table('prizes')->where('id', $row->id)->update(['winner_id' => null, 'prize_status' => 'No', 'updated_at' => now()]);
            if ($winner) {
                DB::table('winners')->where('id', $winner->id)->delete();
                if ($winner->register_id) DB::table('attendees')->where('register_id', $winner->register_id)->update(['winner_status' => 'No']);
            }
        });
        return $this->ok('Winner removed. The prize will be drawn again.', $request);
    }

    /** People marked "not present" on stage can be drawn again. */
    public function resetSkipped(Request $request){
        Cache::forget('raffle.skipped.' . $this->event()->id);
        return $this->ok('Everyone marked "not present" can be drawn again.', $request);
    }

    private function data(Request $request): array
    {
        $event = $this->selected($request);
        return [
            'prizes' => Raffle::prizes($event)->values(),
            'hosts' => Raffle::hosts($event)->pluck('id')->values(),
            'eligibility' => Raffle::eligibility($event),
            'order' => Raffle::order($event),
            'screen' => Raffle::screen($event),
            'sounds' => Raffle::uploadedSounds(),
            'soundOptions' => Raffle::soundOptions(),
            'email' => WinnerEmail::settings($event),
            'entries' => $event ? RaffleEntries::counts($event) : ['total' => 0, 'registered' => 0, 'unknown' => 0],
            'pool' => $event ? Raffle::pool($event, Cache::get('raffle.skipped.' . $event->id, []))->count() : 0,
            'skipped' => $event ? count(Cache::get('raffle.skipped.' . $event->id, [])) : 0,
            'isHost' => Raffle::isHost($request->user(), $event),
        ];
    }

    private function ok(string $message, Request $request)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $this->data($request)]);
    }

    /** The event being set up: ?event=ID (any event), otherwise the current event. */
    private function selected(Request $request): ?\App\Models\Event
    {
        $id = (int) $request->query('event', 0);
        return ($id ? \App\Models\Event::find($id) : null) ?? \App\Support\PinnedEvent::event() ?? Raffle::event();
    }

    private function event()
    {
        $event = $this->selected(request());
        abort_unless($event, 422, 'Create an event first (Admin > Events).');
        return $event;
    }

    private function prize(int $id): object
    {
        $row = DB::table('prizes')->where('id', $id)->where('event_id', $this->event()->id)->first();
        abort_unless($row, 404);
        return $row;
    }

    private function validatePrize(Request $request): array
    {
        return $request->validate([
            'prize' => 'required|string|max:190',
            'prize_type' => 'required|string|max:60',
        ], [], ['prize' => 'prize name', 'prize_type' => 'prize title']);
    }

    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
