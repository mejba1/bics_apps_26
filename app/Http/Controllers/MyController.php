<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;
use App\Models\NfcCard;
use App\Models\Participant;
use App\Support\CardData;
use App\Support\ParticipantAccess;
use App\Support\ParticipantRules;
use App\Support\ParticipantJourney;
use App\Support\IdCard;
use App\Models\Registration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Participant dashboard (/my): sign in with an emailed link or code, see the profile, QR code, card and visits,
 * and edit the fields the organizers allow (App\Support\ParticipantAccess).
 */
class MyController extends Controller
{
    private function me(): Participant
    {
        return Auth::guard('participant')->user();
    }

    /* ---------------------------------------------------------------- sign-in */

    public function login(Request $request){
        if (Auth::guard('participant')->check()) return redirect()->route('my.home');
        return view('my.login', ['portal' => ParticipantAccess::settings()['portal'], 'sentTo' => session('sent_to')]);
    }

    /** Email a link + code. The answer is the same whether or not the address is registered. */
    public function send(Request $request){
        $data = $request->validate(['email' => 'required|email|max:190'], ['email.required' => 'Please enter your email address.', 'email.email' => 'Please enter a valid email address.']);
        $email = mb_strtolower(trim($data['email']));
        $ip = $request->ip();
        // sent after the response, so the time taken does not tell whether the address is registered
        dispatch(fn () => ParticipantAccess::sendLogin($email, $ip))->afterResponse();
        return redirect()->route('my.login')->with('sent_to', $email);
    }

    /** The emailed link opens a confirm page (email scanners that "click" links cannot use up the sign-in). */
    public function link(string $token){
        return view('my.confirm', ['token' => $token]);
    }

    public function useLink(Request $request){
        $token = (string) $request->input('token');
        $p = strlen($token) === 48 ? ParticipantAccess::useToken($token) : null;
        if (!$p) return redirect()->route('my.login')->withErrors(['email' => 'This sign-in link has expired or was already used. Ask for a new one below.']);
        return $this->signIn($request, $p, 'link');
    }

    public function useCode(Request $request){
        $data = $request->validate(['email' => 'required|email', 'code' => 'required|string|max:12'], ['code.required' => 'Please enter the 6-digit code.']);
        $p = ParticipantAccess::useCode($data['email'], $data['code']);
        if (!$p) return redirect()->route('my.login')->with('sent_to', mb_strtolower(trim($data['email'])))->withErrors(['code' => 'That code is not right or has expired.']);
        return $this->signIn($request, $p, 'code');
    }

    /** Email + password (for participants who set one on the Security page). */
    public function usePassword(Request $request){
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string|max:200'],
            ['email.required' => 'Please enter your email address.', 'password.required' => 'Please enter your password.']);
        $p = ParticipantAccess::usePassword($data['email'], $data['password']);
        if (!$p) return redirect()->route('my.login', ['with' => 'password'])->withInput($request->only('email'))
            ->withErrors(['password' => 'Email or password is not right. No password yet? Use "Email me a code".']);
        return $this->signIn($request, $p, 'password');
    }

    private function signIn(Request $request, Participant $p, string $method){
        if (!ParticipantAccess::settings()['portal']) return redirect()->route('my.login');
        Auth::guard('participant')->login($p);
        $request->session()->regenerate();
        $request->session()->put('participant_sv', (int) $p->session_version);
        DB::table('participants')->where('id', $p->id)->update(['last_login_at' => now()]);
        ParticipantAccess::logLogin($p, $method, $request->ip(), $request->userAgent());
        return redirect()->intended(route('my.home'));
    }

    public function logout(Request $request){
        Auth::guard('participant')->logout();
        $request->session()->forget('participant_sv');
        $request->session()->regenerateToken();
        return redirect()->route('my.login')->with('notice', 'You are signed out.');
    }

    /* ---------------------------------------------------------------- pages */

    /** Is it the participant's birthday today (Admin › Birthdays time zone), and is the dashboard greeting on? */
    private function birthdayToday($p): bool
    {
        if (!$p->birth_date || !$p->birthday_wishes || \App\Support\Birthdays::settings()['dashboard'] !== '1') return false;
        return \App\Support\Birthdays::on(\App\Support\Birthdays::now())->contains('id', $p->id);
    }

    /** Their birthday card (the design they were sent this year, or the default one) — only on their birthday. */
    public function birthdayCard(){
        $p = $this->me();
        abort_unless($this->birthdayToday($p), 404);
        $row = \App\Support\Birthdays::people(true)->where('id', $p->id)->first();
        $g = DB::table('birthday_greetings')->where('participant_id', $p->id)->where('year', \App\Support\Birthdays::now()->year)->where('status', 'sent')->orderByDesc('id')->first();
        $card = ($g && $g->card_id ? DB::table('birthday_cards')->find($g->card_id) : null) ?: \App\Support\Birthdays::defaultCard();
        abort_unless($card && $row, 404);
        $opts = $g && $g->card_options ? (json_decode($g->card_options, true) ?: []) : [];
        $vars = \App\Support\Birthdays::vars($row);
        foreach (['headline', 'message', 'signature'] as $k) if (isset($opts[$k])) $opts[$k] = strtr($opts[$k], $vars);
        $base = \App\Support\BirthdayCard::options($card, $opts);
        foreach (['headline', 'message', 'signature'] as $k) $opts[$k] = strtr($base[$k], $vars);
        $jpeg = \App\Support\BirthdayCard::jpeg(\App\Support\Birthdays::person($row), $card, $opts);
        $headers = ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600'];
        if (request()->boolean('download')) $headers['Content-Disposition'] = 'attachment; filename="happy-birthday.jpg"';
        return response($jpeg, 200, $headers);
    }

    public function home(){
        $p = $this->me();
        $j = new ParticipantJourney($p);
        $stats = $p->stats();
        return view('my.home', [
            'p' => $p, 'totals' => $j->totals(), 'next' => $j->next(), 'timeline' => $j->timeline(8), 'milestones' => $j->milestones(),
            'complete' => $j->completeness(), 'canEdit' => ParticipantAccess::canEdit($p), 'card' => $this->nfcCard($p),
            'views' => ($stats['view_link'] ?? 0) + ($stats['view_qr'] ?? 0) + ($stats['view_nfc'] ?? 0),
            'recent' => $j->events()->take(3),
            'birthday' => $this->birthdayToday($p),
            // the sign-in before this one (this one is the current visit)
            'previousLogin' => DB::table('participant_logins')->where('participant_id', $p->id)->orderByDesc('id')->skip(1)->first(),
        ]);
    }

    public function events(){
        $p = $this->me();
        $j = new ParticipantJourney($p);
        return view('my.events', ['p' => $p, 'events' => $j->events(), 'totals' => $j->totals()]);
    }

    /** ID card PDF of one of the participant's own registrations, once assigned by an admin (made on the fly if the file is missing). */
    public function idCard(int $registration){
        $p = $this->me();
        $r = Registration::where('id', $registration)->where('participant_id', $p->id)->firstOrFail();
        abort_unless($r->idcard_assigned_at && $r->qr_code, 404);
        $path = IdCard::path($r);
        if (!is_file(base_path($path))) {
            try { IdCard::make($r); } catch (\Throwable $e) { report($e); abort(503, 'Your ID card could not be made right now. Please try again later.'); }
        }
        return response()->file(base_path($path), ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="ID-card-' . $r->qr_code . '.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    private function nfcCard(Participant $p): ?NfcCard
    {
        return NfcCard::where('participant_id', $p->id)->orderByRaw("FIELD(status, 'active', 'assigned') DESC")->orderByDesc('id')->first();
    }

    /** Card & QR page: QR code, link, NFC card, card preview, visits. */
    public function card(){
        $p = $this->me();
        $template = CardTemplate::forParticipant($p, $p->events()->first());
        $stats = $p->stats();
        $days = DB::table('profile_stats')->where('participant_id', $p->id)->where('day', '>=', now()->subDays(29)->toDateString())
            ->where('metric', 'like', 'view_%')->select('day', DB::raw('SUM(count) as n'))->groupBy('day')->pluck('n', 'day');
        $series = [];
        for ($i = 29; $i >= 0; $i--) { $d = now()->subDays($i)->toDateString(); $series[] = ['day' => $d, 'n' => (int) ($days[$d] ?? 0)]; }
        return view('my.card', [
            'p' => $p, 'card' => $this->nfcCard($p), 'stats' => $stats, 'series' => $series,
            'template' => $template ? ['design' => $template->design] : null, 'cardData' => CardData::for($p),
            'icons' => json_decode((string) file_get_contents(base_path('assets/backend/card-studio/icons.json')), true),
        ]);
    }

    /* ---------------------------------------------------------------- security */

    public function security(){
        $p = $this->me();
        $logins = DB::table('participant_logins')->where('participant_id', $p->id)->orderByDesc('id')->limit(10)->get()
            ->map(fn ($l, $i) => (object) ['method' => $l->method, 'device' => ParticipantAccess::device($l->user_agent), 'ip' => $l->ip,
                'at' => \Carbon\Carbon::parse($l->created_at), 'current' => $i === 0]);
        return view('my.security', ['p' => $p, 'logins' => $logins, 'hasPassword' => (bool) $p->password]);
    }

    /** Set or change the password. Changing needs the current one; afterwards other devices are signed out. */
    public function password(Request $request){
        $p = $this->me();
        $rules = ['password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers(), 'max:200']];
        if ($p->password) $rules['current_password'] = 'required|string';
        $request->validate($rules, ['password.confirmed' => 'The two new passwords are not the same.', 'current_password.required' => 'Please enter your current password.']);
        if ($p->password && !Hash::check((string) $request->input('current_password'), $p->password)) {
            return response()->json(['message' => 'Your current password is not right.', 'errors' => ['current_password' => ['Your current password is not right.']]], 422);
        }
        $local = $p->email ? explode('@', $p->email)[0] : '';
        if (strlen($local) >= 4 && mb_stripos((string) $request->input('password'), $local) !== false) {
            return response()->json(['message' => 'Please choose a password that does not contain your email.', 'errors' => ['password' => ['Please choose a password that does not contain your email.']]], 422);
        }
        $first = !$p->password;
        $p->forceFill(['password' => Hash::make($request->input('password')), 'password_changed_at' => now(), 'session_version' => (int) $p->session_version + 1])->save();
        $request->session()->put('participant_sv', (int) $p->session_version);   // this device stays signed in
        return response()->json(['success' => true, 'message' => $first ? 'Password set. You can now sign in with it or with an emailed code.' : 'Password changed. Your other devices were signed out.']);
    }

    /** Go back to signing in with emailed codes only. */
    public function removePassword(Request $request){
        $p = $this->me();
        $request->validate(['current_password' => 'required|string'], ['current_password.required' => 'Please enter your current password.']);
        if (!$p->password || !Hash::check((string) $request->input('current_password'), $p->password)) {
            return response()->json(['message' => 'Your current password is not right.', 'errors' => ['current_password' => ['Your current password is not right.']]], 422);
        }
        $p->forceFill(['password' => null, 'password_changed_at' => now()])->save();
        return response()->json(['success' => true, 'message' => 'Password removed. Sign in with an emailed code from now on.']);
    }

    public function signOutOthers(Request $request){
        $p = $this->me();
        $p->forceFill(['session_version' => (int) $p->session_version + 1])->save();
        $request->session()->put('participant_sv', (int) $p->session_version);
        return response()->json(['success' => true, 'message' => 'All your other devices are signed out.']);
    }

    /* ---------------------------------------------------------------- editing */

    public function edit(){
        $p = $this->me();
        return view('my.edit', ['p' => $p, 'editable' => ParticipantAccess::editable($p), 'canEdit' => ParticipantAccess::canEdit($p),
            'fields' => Participant::FIELDS]);
    }

    public function update(Request $request){
        $p = $this->me();
        $allowed = ParticipantAccess::editable($p);
        if (!$allowed) return response()->json(['success' => false, 'message' => 'Editing is switched off for your profile. Please contact the organizers.'], 403);
        $rules = array_filter(ParticipantRules::rules(), function ($k) use ($allowed) { return in_array(explode('.', $k)[0], $allowed, true); }, ARRAY_FILTER_USE_KEY);
        // first name may only be left out when it is locked (it is required on the profile)
        $data = $request->validate($rules, ParticipantRules::messages());
        $data = array_intersect_key($data, array_flip($allowed));
        // only what was sent changes; privacy switches not sent keep their current setting
        if (array_key_exists('visibility', $data)) $data['visibility'] = array_merge($p->visibility ?? [], (array) $data['visibility']);
        $mine = (array) session('my_uploads', []);
        $data = ParticipantRules::normalize($data, $p, fn ($path, $field) => $path === $p->getAttribute($field) || in_array($path, $mine, true));
        $before = $p->only(array_keys($data));
        $p->fill($data)->save();
        ParticipantAccess::logChanges($p, $before, 'participant', null, $request->ip());
        return response()->json(['success' => true, 'message' => 'Saved. Your profile link and QR code stay the same.']);
    }

    /** Photo or organization logo. */
    public function upload(Request $request){
        $p = $this->me();
        $field = $request->input('field') === 'logo' ? 'logo' : 'photo';
        if (!in_array($field, ParticipantAccess::editable($p), true)) return response()->json(['message' => 'You cannot change this picture.'], 403);
        $request->validate(['image' => 'required|file|max:5120|mimes:png,jpg,jpeg,webp'], ['image.mimes' => 'Please upload a PNG, JPG or WEBP image.', 'image.max' => 'The image must be smaller than 5 MB.']);
        $file = $request->file('image');
        $ext = ['png' => 'png', 'webp' => 'webp'][strtolower($file->getClientOriginalExtension())] ?? 'jpg';
        $name = Str::lower(Str::random(16)) . '.' . $ext;
        $file->move(base_path('upload/participants'), $name);
        $path = 'upload/participants/' . $name;
        $size = @getimagesize(base_path($path));
        if (!$size) { @unlink(base_path($path)); return response()->json(['message' => 'This file is not an image.'], 422); }
        if (max($size[0], $size[1]) > 1200) \App\Support\Thumb::shrink(base_path($path), base_path($path), 1200);
        session()->push('my_uploads', $path);
        return response()->json(['success' => true, 'path' => $path, 'url' => asset($path)]);
    }
}
