<?php

namespace App\Http\Controllers;

use App\Support\FormGuard;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** System › Platform settings (permission "system.settings"): participant ID prefix and how long logs are kept. */
class PlatformSettingsController extends Controller
{
    public const PERIODS = [0 => 'Keep forever', 30 => '30 days', 90 => '90 days', 180 => '6 months', 365 => '1 year', 730 => '2 years', 1825 => '5 years'];

    public function index(){
        $info = fn ($table, $col) => ['rows' => DB::table($table)->count(), 'oldest' => DB::table($table)->min($col)];
        return view('backend.pages.platform-settings', [
            's' => PlatformSettings::all(), 'periods' => static::PERIODS,
            'storage' => ['audit' => $info('audit_logs', 'created_at'), 'stats' => $info('profile_stats', 'day'), 'logins' => $info('participant_logins', 'created_at')],
            'nextCode' => \App\Models\Participant::nextIdentity()[1],
            'guard' => FormGuard::settings(), 'turnstileOn' => FormGuard::turnstileOn(), 'blocked' => FormGuard::blockedCount(7),
        ]);
    }

    public function update(Request $request){
        $keys = implode(',', array_keys(static::PERIODS));
        $data = $request->validate([
            'prefix' => ['required', 'string', 'regex:/^[A-Za-z]{2,10}$/'],
            'audit_days' => 'required|integer|in:' . $keys, 'stats_days' => 'required|integer|in:' . $keys, 'logins_days' => 'required|integer|in:' . $keys,
            'lock_days' => 'sometimes|integer|between:0,60',
            'block_disposable' => 'sometimes|boolean',
            'turnstile_site' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'turnstile_secret' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ], ['prefix.regex' => 'Use 2 to 10 letters (A–Z), e.g. MBR or BICS.', 'turnstile_site.regex' => 'That does not look like a Turnstile site key.', 'turnstile_secret.regex' => 'That does not look like a Turnstile secret key.']);
        PlatformSettings::save($data);

        // Turnstile: empty site key switches it off; an empty secret keeps the saved one
        $before = FormGuard::turnstileOn();
        $guard = ['block_disposable' => (bool) ($data['block_disposable'] ?? FormGuard::settings()['block_disposable'])];
        if (array_key_exists('turnstile_site', $data)) {
            $site = (string) ($data['turnstile_site'] ?? '');
            $secret = (string) ($data['turnstile_secret'] ?? '');
            if ($site === '') { $guard['turnstile_site'] = ''; $guard['turnstile_secret'] = ''; }
            else {
                if ($secret === '' && FormGuard::settings()['turnstile_secret'] === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages(['turnstile_secret' => 'Please paste the Turnstile secret key as well.']);
                }
                $guard['turnstile_site'] = $site;
                if ($secret !== '') $guard['turnstile_secret'] = $secret;
            }
        }
        FormGuard::save($guard);
        return response()->json(['success' => true, 'reload' => $before !== FormGuard::turnstileOn(),
            'message' => 'Settings saved. New participants get IDs like ' . \App\Models\Participant::nextIdentity()[1] . '.']);
    }

    /** Remove old entries now (also runs every night). */
    public function prune(){
        $done = array_filter(PlatformSettings::prune());
        return response()->json(['success' => true, 'message' => $done ? 'Removed: ' . collect($done)->map(fn ($n, $w) => number_format($n) . ' ' . $w)->implode(', ') . '.' : 'Nothing was old enough to remove.']);
    }
}
