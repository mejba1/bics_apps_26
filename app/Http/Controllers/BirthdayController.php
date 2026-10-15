<?php

namespace App\Http\Controllers;

use App\Support\BirthdayCard;
use App\Support\Birthdays;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Admin › Engagement › Birthdays (permission "birthdays.manage"). See App\Support\Birthdays. */
class BirthdayController extends Controller
{
    public function index(Request $request){
        $s = Birthdays::settings();
        $now = Birthdays::now();
        $upcoming = Birthdays::upcoming(30);
        $today = $upcoming->where('in_days', 0)->values();
        $status = Birthdays::statusThisYear($upcoming->pluck('id')->all());
        $year = $now->year;
        $sentYear = DB::table('birthday_greetings')->where('year', $year)->where('status', 'sent')->where('mode', '!=', 'test')->count();
        $failedYear = DB::table('birthday_greetings')->where('year', $year)->where('status', 'failed')->where('mode', '!=', 'test')->count();
        $all = Birthdays::people(true)->get();
        $last = DB::table('birthday_greetings')->where('status', 'sent')->where('mode', '!=', 'test')->selectRaw('participant_id, MAX(sent_at) at')->groupBy('participant_id')->pluck('at', 'participant_id');
        $people = $all->map(function ($p) use ($now, $last) {
            $p->next = Birthdays::nextBirthday($p->birth_date, $now->copy()->startOfDay());
            $p->in_days = (int) $now->copy()->startOfDay()->diffInDays($p->next);
            $p->turns = (int) \Carbon\Carbon::parse($p->birth_date)->diffInYears($p->next);
            $p->last_wished = $last[$p->id] ?? null;
            return $p;
        })->sortBy('in_days')->values();
        $history = DB::table('birthday_greetings as g')->leftJoin('participants as p', 'p.id', '=', 'g.participant_id')->leftJoin('users as u', 'u.id', '=', 'g.sent_by')
            ->leftJoin('birthday_cards as c', 'c.id', '=', 'g.card_id')
            ->select('g.*', 'p.first_name', 'p.last_name', 'p.title', 'p.code', 'p.photo', 'u.name as by_name', 'c.name as card_name')
            ->orderByDesc('g.id')->limit(300)->get();
        return view('backend.pages.birthdays', [
            's' => $s, 'now' => $now, 'today' => $today, 'upcoming' => $upcoming, 'status' => $status, 'people' => $people, 'history' => $history,
            'cards' => Birthdays::cards(), 'byMonth' => Birthdays::byMonth(),
            'kpi' => [
                'today' => $today->where('birthday_wishes', true)->count(),
                'week' => $upcoming->where('in_days', '<=', 6)->where('birthday_wishes', true)->count(),
                'month' => $upcoming->filter(fn ($p) => $p->next->month === $now->month && $p->next->year === $now->year && $p->birthday_wishes)->count(),
                'sent' => $sentYear, 'failed' => $failedYear,
                'reach' => $all->where('birthday_wishes', true)->filter(fn ($p) => filter_var($p->email, FILTER_VALIDATE_EMAIL))->count(),
                'off' => $all->where('birthday_wishes', false)->count(), 'noEmail' => $all->filter(fn ($p) => !filter_var($p->email, FILTER_VALIDATE_EMAIL))->count(),
                'total' => $all->count(), 'noDob' => DB::table('participants')->whereNull('deleted_at')->whereNull('merged_into_id')->where('status', 'active')->whereNull('birth_date')->count(),
            ],
            'timezones' => timezone_identifiers_list(),
            'tab' => in_array($request->query('tab'), ['overview', 'people', 'designs', 'message', 'history'], true) ? $request->query('tab') : 'overview',
        ]);
    }

    /** The master switch. */
    public function toggle(Request $request){
        $on = $request->boolean('enabled');
        Birthdays::save(['enabled' => $on]);
        $s = Birthdays::settings();
        return response()->json(['success' => true, 'enabled' => $on, 'message' => $on
            ? 'Birthday wishes are on. Cards go out every day at ' . $s['send_time'] . ' (' . $s['timezone'] . ').'
            : 'Birthday wishes are off. Nothing will be sent automatically.']);
    }

    public function settings(Request $request){
        $data = $request->validate([
            'send_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'subject' => 'required|string|max:150',
            'headline' => 'required|string|max:40',
            'email_body' => 'required|string|max:3000',
            'card_message' => 'nullable|string|max:160',
            'signature' => 'nullable|string|max:60',
            'dashboard' => 'boolean',
        ], ['send_time.regex' => 'Choose a time like 09:00.']);
        Birthdays::save($data + ['dashboard' => $request->boolean('dashboard')]);
        static::clearThumbs();
        return response()->json(['success' => true, 'message' => 'Saved. The next cards use these texts.']);
    }

    /** Wishes on / off for one person. */
    public function wishes(Request $request, int $participant){
        $on = $request->boolean('on');
        $n = DB::table('participants')->where('id', $participant)->update(['birthday_wishes' => $on]);
        abort_unless($n || DB::table('participants')->where('id', $participant)->exists(), 404);
        return response()->json(['success' => true, 'on' => $on, 'message' => $on ? 'Birthday wishes on for this person.' : 'This person will not get birthday wishes.']);
    }

    public function search(Request $request){
        $q = trim((string) $request->query('q'));
        $rows = Birthdays::people(true)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('first_name', 'like', "%$q%")->orWhere('last_name', 'like', "%$q%")
                ->orWhere('email', 'like', "%$q%")->orWhere('code', 'like', "%$q%")->orWhereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ["%$q%"])))
            ->orderBy('first_name')->limit(12)->get();
        return response()->json(['data' => $rows->map(fn ($p) => static::row($p))]);
    }

    /* ---------------- pictures ---------------- */

    /** Live card preview (JPEG) for the composer / design editor. */
    public function preview(Request $request){
        $card = $this->cardFrom($request);
        $p = $request->filled('participant_id') ? Birthdays::people(true)->where('id', $request->integer('participant_id'))->first() : null;
        $person = $p ? Birthdays::person($p) : Birthdays::sample();
        $over = $this->overrides($request);
        if ($p) { $vars = Birthdays::vars($p); foreach (['headline', 'message', 'signature'] as $k) if (isset($over[$k])) $over[$k] = strtr($over[$k], $vars); }
        $jpeg = BirthdayCard::jpeg($person, $card, $over, 82);
        $headers = ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store'];
        if ($request->boolean('download')) $headers['Content-Disposition'] = 'attachment; filename="happy-birthday-' . \Illuminate\Support\Str::slug($person['first_name'] ?: 'card') . '.jpg"';
        return response($jpeg, 200, $headers);
    }

    /** Gallery thumbnail of a design (cached until the design or texts change). */
    public function thumb(int $card){
        $c = DB::table('birthday_cards')->where('id', $card)->first();
        abort_unless($c, 404);
        $dir = storage_path('app/birthday-thumbs'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $dir . '/' . $c->id . '-' . substr(md5($c->updated_at . json_encode(Birthdays::settings())), 0, 10) . '.jpg';
        if (!is_file($file)) {
            foreach (glob($dir . '/' . $c->id . '-*.jpg') as $old) @unlink($old);
            $img = BirthdayCard::draw(Birthdays::sample(), $c);
            $w = 600; $h = (int) round(imagesy($img) * $w / imagesx($img));
            $small = imagecreatetruecolor($w, $h); imagecopyresampled($small, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));
            imagejpeg($small, $file, 82); imagedestroy($img); imagedestroy($small);
        }
        return response()->file($file, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=60']);
    }

    /** The email as it will look (for the Message tab and the composer). */
    public function previewEmail(Request $request){
        $p = $request->filled('participant_id') ? Birthdays::people(true)->where('id', $request->integer('participant_id'))->first() : null;
        $vars = $p ? Birthdays::vars($p) : ['{first_name}' => 'Sarah', '{last_name}' => 'Rahman', '{name}' => 'Dr Sarah Rahman', '{age}' => '42', '{age_ordinal}' => '42nd', '{events}' => '3', '{since}' => '2023', '{participant_id}' => 'MBR-000123'];
        $s = Birthdays::settings();
        $card = $this->cardFrom($request);
        $q = array_filter(['card_id' => $card->id ?? null, 'participant_id' => $p->id ?? null] + $this->overrides($request), fn ($v) => $v !== null && $v !== '');
        return view('mail.birthday', [
            'body' => (string) $request->input('email_body', $s['email_body']), 'vars' => $vars,
            'subjectLine' => \App\Support\EmailTemplate::text((string) $request->input('subject', $s['subject']), $vars),
            'cardSrc' => route('birthdays.preview') . '?' . http_build_query($q), 'card' => '',
        ]);
    }

    /* ---------------- sending ---------------- */

    public function send(Request $request){
        $data = $request->validate([
            'participant_id' => 'required|integer', 'card_id' => 'required|integer|exists:birthday_cards,id',
            'subject' => 'nullable|string|max:150', 'email_body' => 'nullable|string|max:3000', 'test' => 'boolean',
        ]);
        $p = Birthdays::people(true)->where('id', $data['participant_id'])->first();
        if (!$p) return response()->json(['message' => 'This participant has no date of birth or is not active.'], 422);
        $test = $request->boolean('test');
        $me = auth()->user();
        [$ok, $msg] = Birthdays::send($p, [
            'mode' => $test ? 'test' : 'manual', 'card_id' => $data['card_id'], 'card' => $this->overrides($request),
            'subject' => ($data['subject'] ?? null) ?: null, 'email_body' => ($data['email_body'] ?? null) ?: null, 'to' => $test ? $me->email : null,
        ]);
        return response()->json(['success' => $ok, 'message' => $test && $ok ? 'Test sent to ' . $me->email . '.' : $msg], $ok ? 200 : 422);
    }

    /** Send today's wishes now (ignores the send time; still once per person per year). */
    public function run(){
        [$sent, $failed] = Birthdays::run(true, true);
        return response()->json(['success' => true, 'message' => $sent || $failed
            ? "Sent $sent birthday card" . ($sent === 1 ? '' : 's') . ($failed ? ", $failed failed (see History)" : '') . '.'
            : 'Nobody left to wish today — everyone with a birthday today already has their card.']);
    }

    public function resend(int $greeting){
        $g = DB::table('birthday_greetings')->where('id', $greeting)->first();
        abort_unless($g, 404);
        $p = Birthdays::people(true)->where('id', $g->participant_id)->first();
        if (!$p) return response()->json(['message' => 'The participant is no longer active.'], 422);
        [$ok, $msg] = Birthdays::send($p, ['mode' => $g->mode === 'auto' ? 'auto' : 'manual', 'card_id' => $g->card_id,
            'card' => $g->card_options ? json_decode($g->card_options, true) : [], 'email_body' => $g->message ?: null]);
        return response()->json(['success' => $ok, 'message' => $msg], $ok ? 200 : 422);
    }

    /* ---------------- designs ---------------- */

    public function upload(Request $request){
        $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:6144', 'name' => 'nullable|string|max:120'],
            ['image.max' => 'The picture must be smaller than 6 MB.', 'image.mimes' => 'Use a JPG, PNG or WebP picture.']);
        $file = $request->file('image');
        [$w, $h] = @getimagesize($file->getRealPath()) ?: [0, 0];
        if ($w < 600 || $h < 300) return response()->json(['message' => 'The picture is too small — use at least 1200 px wide for a sharp card.', 'errors' => ['image' => ['Too small.']]], 422);
        if ($h > $w * 1.6) return response()->json(['message' => 'The picture is very tall. Use a landscape or square design (for example 1200 × 800 px).', 'errors' => ['image' => ['Too tall.']]], 422);
        $dir = 'upload/birthday-cards'; if (!is_dir(base_path($dir))) @mkdir(base_path($dir), 0775, true);
        // re-encode: strips anything hidden in the file and keeps the size sensible
        $img = BirthdayCard::load($file->getRealPath());
        $name = $dir . '/' . date('Ymd-His') . '-' . \Illuminate\Support\Str::random(6) . '.jpg';
        if (imagesx($img) > 2400) { $nh = (int) round(imagesy($img) * 2400 / imagesx($img)); $r = imagecreatetruecolor(2400, $nh); imagecopyresampled($r, $img, 0, 0, 0, 0, 2400, $nh, imagesx($img), imagesy($img)); imagedestroy($img); $img = $r; }
        imagejpeg($img, base_path($name), 92); imagedestroy($img);
        $id = DB::table('birthday_cards')->insertGetId(['name' => $request->input('name') ?: 'My design ' . date('j M'), 'kind' => 'upload', 'image' => $name,
            'options' => json_encode(['name_y' => 62, 'name_size' => 64, 'name_color' => '#ffffff', 'align' => 'center', 'show_message' => true, 'shadow' => true]),
            'is_default' => false, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['success' => true, 'id' => $id, 'card' => DB::table('birthday_cards')->find($id), 'message' => 'Design uploaded. Place the name where it fits your artwork.']);
    }

    public function updateCard(Request $request, int $card){
        $c = DB::table('birthday_cards')->where('id', $card)->first();
        abort_unless($c, 404);
        $request->validate(['name' => 'required|string|max:120']);
        $opts = $this->overrides($request, true);
        DB::table('birthday_cards')->where('id', $card)->update(['name' => $request->input('name'), 'options' => json_encode($opts), 'updated_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Design saved.']);
    }

    public function makeDefault(int $card){
        abort_unless(DB::table('birthday_cards')->where('id', $card)->exists(), 404);
        DB::table('birthday_cards')->update(['is_default' => false]);
        DB::table('birthday_cards')->where('id', $card)->update(['is_default' => true, 'updated_at' => now()]);
        return response()->json(['success' => true, 'message' => 'This design is now used for the automatic wishes.']);
    }

    public function destroyCard(int $card){
        $c = DB::table('birthday_cards')->where('id', $card)->first();
        abort_unless($c, 404);
        if ($c->kind !== 'upload') return response()->json(['message' => 'Built-in designs cannot be deleted.'], 422);
        if ($c->is_default) return response()->json(['message' => 'Choose another default design first.'], 422);
        DB::table('birthday_cards')->where('id', $card)->delete();
        if ($c->image && str_starts_with($c->image, 'upload/birthday-cards/')) @unlink(base_path($c->image));
        return response()->json(['success' => true, 'message' => 'Design deleted.']);
    }

    /* ---------------- helpers ---------------- */

    private function cardFrom(Request $request): object
    {
        $c = $request->filled('card_id') ? DB::table('birthday_cards')->where('id', $request->integer('card_id'))->first() : null;
        return $c ?: (Birthdays::defaultCard() ?: (object) ['kind' => 'preset', 'preset' => 'confetti', 'options' => '[]']);
    }

    /** Card options sent by the editor / composer (only known keys, sane values). */
    private function overrides(Request $request, bool $forSave = false): array
    {
        $o = [];
        foreach (['headline' => 40, 'message' => 160, 'signature' => 60] as $k => $max) {
            if ($request->has($k)) $o[$k] = mb_substr(trim((string) $request->input($k)), 0, $max);
        }
        foreach (['show_photo', 'show_age', 'show_logo', 'show_message', 'shadow'] as $k) if ($request->has($k)) $o[$k] = $request->boolean($k);
        if ($request->has('name_y')) $o['name_y'] = max(3, min(97, (float) $request->input('name_y')));
        if ($request->has('name_size')) $o['name_size'] = max(20, min(160, (int) $request->input('name_size')));
        foreach (['name_color', 'message_color'] as $k) if (preg_match('/^#[0-9a-f]{6}$/i', (string) $request->input($k))) $o[$k] = $request->input($k);
        if (in_array($request->input('align'), ['left', 'center', 'right'], true)) $o['align'] = $request->input('align');
        return $o;
    }

    private static function clearThumbs(): void
    {
        foreach (glob(storage_path('app/birthday-thumbs/*.jpg')) ?: [] as $f) @unlink($f);
    }

    public static function row($p): array
    {
        $photo = $p->photo && is_file(base_path($p->photo)) ? \App\Support\Thumb::url($p->photo, 480) : null;
        $b = \Carbon\Carbon::parse($p->birth_date);
        return ['id' => $p->id, 'code' => $p->code, 'name' => trim(($p->title ? $p->title . ' ' : '') . $p->first_name . ' ' . $p->last_name),
            'email' => $p->email, 'photo' => $photo, 'birthday' => $b->format('j M'), 'wishes' => (bool) $p->birthday_wishes,
            'initials' => mb_strtoupper(mb_substr($p->first_name, 0, 1) . mb_substr($p->last_name, 0, 1))];
    }
}
