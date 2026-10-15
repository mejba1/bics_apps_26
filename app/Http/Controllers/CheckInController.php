<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\CheckIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/** Public self check-in page opened from the venue posters: /checkin/{event-slug}/{token}. See App\Support\CheckIn. */
class CheckInController extends Controller
{
    private function event(string $slug, string $token): array
    {
        $event = Event::where('slug', $slug)->first();
        $s = $event ? CheckIn::settings($event) : null;
        if (!$event || !hash_equals($s['token'], $token)) abort(404);
        return [$event, $s];
    }

    public function show(Request $request, string $slug, string $token){
        [$event, $s] = $this->event($slug, $token);
        [$open, $closed] = CheckIn::state($event, $s);
        return response()->view('checkin.page', [
            'event' => $event, 's' => $s, 'open' => $open, 'closedMessage' => $closed, 'kiosk' => $request->boolean('kiosk'),
            'action' => route('checkin.submit', [$slug, $token]), 'findAction' => route('checkin.find', [$slug, $token]),
        ])->header('X-Robots-Tag', 'noindex');
    }

    public function submit(Request $request, string $slug, string $token){
        [$event, $s] = $this->event($slug, $token);
        $fail = fn ($status, $message, $code = 422, $extra = []) => response()->json(['status' => $status, 'message' => $message] + $extra, $code);

        [$open, $closed] = CheckIn::state($event, $s);
        if (!$open) return $fail('closed', $closed);

        $number = preg_replace('/\D/', '', (string) $request->input('number'));
        if (strlen($number) < 4 || strlen($number) > 12) return $fail('invalid', 'Please enter your registration number (the number in your confirmation email).');

        // too many wrong tries from this phone / for this number
        $key = 'checkin-fail:' . $event->id . ':' . sha1($request->ip() . '|' . $number);
        $ipKey = 'checkin-fail-ip:' . $event->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 15)) {
            return $fail('locked', 'Too many tries. Please wait a few minutes, or ask at the help desk.', 429);
        }

        $r = CheckIn::find($event, $number);
        if (!$r) {
            RateLimiter::hit($ipKey, 600);
            $other = CheckIn::otherEvent($number);
            return $other
                ? $fail('other_event', 'This number is from your ' . $other->event_short . ' registration. You are not registered for ' . $event->event_short . ' yet.', 422, ['register' => url('/registration')])
                : $fail('not_found', 'We could not find this registration number. Please check it, or ask at the help desk.');
        }
        if ($s['approved_only'] && $r->status && strcasecmp($r->status, 'Approved') !== 0) {
            return $fail('not_approved', 'Your registration is not approved yet. Please go to the help desk.');
        }

        // already checked in: no second check-in, but show who they are (only after the extra detail matches, if one is asked)
        if ($s['extra'] !== 'none' && trim((string) $request->input('extra')) === '') {
            return $fail('extra', 'Please also enter your ' . mb_strtolower(CheckIn::EXTRA[$s['extra']]) . '.');
        }
        $extraOk = CheckIn::extraMatches($r, $s['extra'], $request->input('extra'));
        if (!$extraOk) {
            RateLimiter::hit($key, 900); RateLimiter::hit($ipKey, 600);
            $label = mb_strtolower(CheckIn::EXTRA[$s['extra']]);
            return $fail('extra', 'The ' . $label . ' does not match this registration number.');
        }

        $meta = ['ip' => $request->ip(), 'device' => substr(hash('sha256', (string) $request->cookie('bics_dev') . '|' . $request->userAgent()), 0, 40)];
        if ($s['location']) {
            $lat = $request->input('lat'); $lng = $request->input('lng');
            if (!is_numeric($lat) || !is_numeric($lng)) return $fail('location', 'Please allow location access — check-in only works at the venue.');
            if (!is_numeric($s['lat']) || !is_numeric($s['lng'])) Log::warning('BICS check-in: location check is on but the venue position is not set', ['event' => $event->id]);
            else {
                $d = CheckIn::distance((float) $lat, (float) $lng, (float) $s['lat'], (float) $s['lng']);
                if ($d > $s['radius'] + min(1000, (int) $request->input('acc', 0))) return $fail('far', 'You seem to be away from the venue. Check-in works at ' . ($event->event_venue ?: 'the venue') . ' only.');
                $meta += ['lat' => $lat, 'lng' => $lng, 'distance' => $d];
            }
        }

        RateLimiter::clear($key);
        [$new, $row] = CheckIn::confirm($r, $request->boolean('kiosk') ? 'kiosk' : 'self', $meta, $s['certificate'] !== 'never');
        $tz = CheckIn::tz($event);
        return response()->json([
            'status' => $new ? 'checked_in' : 'already',
            'at' => \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('H:i'),
            'day' => \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('l j F'),
            'profile' => CheckIn::profile($r, $s, $event),
        ])->cookie('bics_dev', $request->cookie('bics_dev') ?: \Illuminate\Support\Str::random(24), 60 * 24 * 30, null, null, $request->isSecure(), true, false, 'lax');
    }

    /** "Forgot your number?": email it. The answer is the same whether or not the email is registered. */
    public function find(Request $request, string $slug, string $token){
        [$event, $s] = $this->event($slug, $token);
        abort_unless($s['forgot'], 404);
        $email = mb_strtolower(trim((string) $request->input('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return response()->json(['message' => 'Please enter a valid email address.'], 422);
        $done = ['success' => true, 'message' => 'If ' . $email . ' is registered for ' . $event->event_short . ', your registration number is on its way. Check your inbox (and spam).'];
        $key = 'checkin-find:' . sha1($email);
        if (RateLimiter::tooManyAttempts($key, 2)) return response()->json($done);
        RateLimiter::hit($key, 900);
        $r = CheckIn::registrations($event)->whereRaw('LOWER(email) = ?', [$email])->orderByDesc('id')->first();
        if ($r) {
            $number = (string) $r->qr_code; $name = trim($r->firstname . ' ' . $r->lastname); $short = $event->event_short;
            dispatch(function () use ($email, $name, $number, $short) {
                try {
                    Mail::raw("Dear $name,\n\nYour registration number for $short is: $number\n\nType it on the check-in page at the venue.\n\n— The BICS team", function ($m) use ($email, $name, $short, $number) {
                        $m->to($email, $name)->subject("Your $short registration number: $number");
                    });
                } catch (\Throwable $e) { Log::warning('BICS check-in number email failed', ['error' => $e->getMessage()]); }
            })->afterResponse();
        }
        return response()->json($done);
    }
}
