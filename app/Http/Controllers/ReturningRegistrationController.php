<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Services\RegistrationService;
use App\Support\EventSettings;
use App\Support\ParticipantAccess;
use App\Support\ReturningRegistration as RR;
use Illuminate\Http\Request;

/**
 * /registration › "Already joined a BICS event?": email / participant ID / registration number → one-time code by
 * email → the form is filled with the saved details (name, email, date of birth and gender locked) → submit for
 * the current event. Same rules, photo handling and confirmation email as a new registration.
 */
class ReturningRegistrationController extends Controller
{
    private function closed(): ?string
    {
        $w = EventSettings::registration();
        if ($w['state'] === 'open') return null;
        return $w['state'] === 'upcoming'
            ? 'Registration opens on ' . $w['opens']->format('j F Y, H:i') . '.'
            : EventSettings::get('registration_closed_message', 'Registration for this event is now closed.');
    }

    /** Step 1: find the person and email a code. */
    public function send(Request $request){
        if ($m = $this->closed()) return response()->json(['message' => $m], 422);
        $q = trim((string) $request->validate(['lookup' => 'required|string|max:190'], ['lookup.required' => 'Enter your email, participant ID or registration number.'])['lookup']);
        $p = RR::find($q);
        if (!$p) {
            return response()->json(['message' => 'We could not find an earlier registration with that. Check it, or choose "New registration".',
                'errors' => ['lookup' => ['We could not find an earlier registration with that.']]], 422);
        }
        if (!RR::sendCode($p, $request->ip())) {
            return response()->json(['message' => 'We could not send a code right now (or one was sent a moment ago). Please wait a few minutes and try again.'], 429);
        }
        $request->session()->put(RR::SESSION . '.pending', $p->id);
        return response()->json(['success' => true, 'to' => RR::mask($p->email),
            'message' => 'We sent a 6-digit code to ' . RR::mask($p->email) . '.']);
    }

    /** Step 2: check the code; answer with the saved details (or "already registered"). */
    public function verify(Request $request){
        if ($m = $this->closed()) return response()->json(['message' => $m], 422);
        $data = $request->validate(['code' => 'required|string|max:12'], ['code.required' => 'Enter the 6-digit code from the email.']);
        $pid = (int) $request->session()->get(RR::SESSION . '.pending');
        $p = $pid ? Participant::find($pid) : null;
        if (!$p) return response()->json(['message' => 'Please start again: enter your email or ID and ask for a code.', 'restart' => true], 422);
        if (!ParticipantAccess::useCode($p->email, $data['code'])) {
            return response()->json(['message' => 'That code is not right or has expired.', 'errors' => ['code' => ['That code is not right or has expired.']]], 422);
        }
        $request->session()->forget(RR::SESSION . '.pending');
        if ($done = RR::alreadyRegistered($p)) {
            return response()->json(['success' => true, 'already' => true, 'code' => (string) $done->qr_code, 'name' => $p->name,
                'dashboard' => ParticipantAccess::settings()['portal'] ? route('my.login') : null,
                'message' => 'You are already registered for ' . EventSettings::get('event_short') . '.']);
        }
        $request->session()->put(RR::SESSION, ['pid' => $p->id, 'until' => now()->addMinutes(RR::SESSION_MINUTES)->timestamp]);
        return response()->json(['success' => true, 'data' => RR::prefill($p), 'locked' => RR::LOCKED, 'minutes' => RR::SESSION_MINUTES]);
    }

    /** Step 3: register for the current event with the (checked / updated) details. */
    public function submit(Request $request, RegistrationService $service){
        if ($request->filled('reg_hp_check')) return response()->json(['message' => 'Your submission could not be verified. Please reload the page and try again.', 'errors' => ['form' => ['Please reload the page.']]], 422);
        if ($m = $this->closed()) return response()->json(['message' => $m, 'errors' => ['form' => [$m]]], 422);
        $s = $request->session()->get(RR::SESSION);
        $p = $s && ($s['until'] ?? 0) > time() ? Participant::find($s['pid']) : null;
        if (!$p || $p->status !== 'active') {
            $request->session()->forget(RR::SESSION);
            return response()->json(['message' => 'Your verification has expired. Please ask for a new code.', 'errors' => ['form' => ['Your verification has expired. Please ask for a new code.']], 'restart' => true], 422);
        }
        if ($done = RR::alreadyRegistered($p)) {
            return response()->json(['message' => 'You are already registered for this event (registration no. ' . $done->qr_code . ').', 'errors' => ['email' => ['Already registered for this event.']]], 422);
        }
        // identity comes from the earlier records, whatever the browser sends (only blanks there may be filled in)
        $known = RR::prefill($p);
        $locked = [];
        foreach (RR::LOCKED as $k) if (!empty($known[$k])) $locked[$k] = $known[$k];
        $request->merge($locked);
        [$rules, $messages, $attributes] = $service->validationRules($request);
        $input = $request->validate($rules, $messages, $attributes);
        if ($service->isDuplicate($input['email'])) {
            return response()->json(['message' => 'This email is already registered for this event.', 'errors' => ['email' => ['This email is already registered for this event.']]], 422);
        }
        $result = $service->register($input + $request->only('countryCode'), $request->file('image'), ['photo_copy' => $request->input('keep_photo') === '0' ? null : RR::photoPath($p)]);
        $request->session()->forget(RR::SESSION);
        $data = $result['registration'];
        return response()->json([
            'success' => true, 'returning' => true,
            'code' => (string) $data->qr_code, 'name' => trim($data->title . ' ' . $data->firstname . ' ' . $data->lastname), 'email' => $data->email,
            'email_sent' => $result['email_sent'], 'message' => $result['message'], 'card_url' => $result['card_url'],
        ]);
    }

    /** "Not you? / start again". */
    public function reset(Request $request){
        $request->session()->forget([RR::SESSION, RR::SESSION . '.pending']);
        return response()->json(['success' => true]);
    }
}
