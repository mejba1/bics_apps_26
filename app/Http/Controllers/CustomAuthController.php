<?php

namespace App\Http\Controllers;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CustomAuthController extends Controller
{
    public function login(){
        return view('backend.auth.login');
    }

    public function customeLogin(Request $request){
        $request->validate([
            'email'=>'required|string|max:190',
            'password'=>'required|string|max:200',
        ]);

        $key = 'admin-login:' . sha1(mb_strtolower(trim((string) $request->email)) . '|' . $request->ip());
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 5)) {
            $mins = (int) ceil(\Illuminate\Support\Facades\RateLimiter::availableIn($key) / 60);
            return back()->withInput($request->only('email'))->with('error', "Too many failed sign-ins. Please try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.');
        }

        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
            'status' => 'active',
        ];

       // $credentials = $request->only('email','password');
        //dd($credentials);

        if(Auth::attempt($credentials, $request->boolean('remember'))){
            \Illuminate\Support\Facades\RateLimiter::clear($key);
            $request->session()->regenerate();
            // First page this user's role allows
            return redirect(\App\Support\Permissions::homeFor(Auth::user()))->with('success','SignIn Success');
        }

        \Illuminate\Support\Facades\RateLimiter::hit($key, 15 * 60);
        \Illuminate\Support\Facades\Log::notice('BICS admin sign-in failed', ['email' => substr((string) $request->email, 0, 190), 'ip' => $request->ip()]);
        return back()->withInput($request->only('email'))->with('error', 'Email Or Password is incorrect!');
    }


    public function index(){
        $user = Auth::user();
        // No dashboard permission: go to the first page the role allows, or explain there is no access yet
        if (!$user->can('registrations.view')) {
            $home = \App\Support\Permissions::homeFor($user);
            return $home !== '/home' ? redirect($home) : view('backend.pages.no-access');
        }
        return view('backend.pages.home', $this->dashboard(request('venue')));
    }

    /**
     * Dashboard numbers for one event (venue code), or every event with "all".
     * Default: the user's pinned event (see App\Support\PinnedEvent).
     */
    private function dashboard(?string $venue): array
    {
        $current = \App\Support\EventSettings::get('venue_code', config('bics.venue'));
        $venues = \App\Support\PinnedEvent::venues();
        $pinned = \App\Support\PinnedEvent::get();
        // Opens on the user's pinned event (the current event unless they pinned another)
        if (!$venue || ($venue !== 'all' && !$venues->contains('venue', $venue))) $venue = $pinned;

        $scope = fn () => DB::table('registrations')->when($venue !== 'all', fn ($q) => $q->where('venue', $venue));
        $total = $scope()->count();
        $pct = fn ($n) => $total ? (int) round($n / $total * 100) : 0;

        // Registrations per day over the last 30 days that had activity (ending today, or at the last registration)
        $lastAt = $scope()->max('created_at');
        $end = now()->endOfDay();
        if ($lastAt && \Carbon\Carbon::parse($lastAt)->lessThan(now()->subDays(30))) {
            $end = \Carbon\Carbon::parse($lastAt)->endOfDay(); // past event: show its own last 30 days
        }
        $start = $end->copy()->subDays(29)->startOfDay();
        $perDay = $scope()->whereBetween('created_at', [$start, $end])
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as n'))->groupBy('d')->pluck('n', 'd');
        $series = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $series[] = ['x' => $day->format('Y-m-d'), 'y' => (int) ($perDay[$day->format('Y-m-d')] ?? 0)];
        }

        $prizeScope = fn () => DB::table('prizes')->when($venue !== 'all', fn ($q) => $q->where('prize_venue', $venue));
        $verified = $scope()->where('verify_status', 'Yes')->count();
        $gifts = $scope()->where('gift', 'Approved')->count();
        $emailed = $scope()->where('email_notification', 'Yes')->count();
        $canList = Auth::user()->can('registrations.list');

        return [
            'venue' => $venue,
            'currentVenue' => $current,
            'pinnedVenue' => $pinned,
            'venues' => $venues,
            'stats' => [
                'total' => $total,
                'today' => $scope()->whereDate('created_at', today())->count(),
                'week' => $scope()->where('created_at', '>=', now()->subDays(7))->count(),
                'countries' => $scope()->distinct()->count('country'),
                'verified' => $verified, 'verified_pct' => $pct($verified),
                'gifts' => $gifts, 'gifts_pct' => $pct($gifts),
                'emailed' => $emailed, 'emailed_pct' => $pct($emailed),
                'prizes' => $prizeScope()->count(),
                'prizes_given' => $prizeScope()->where('prize_status', 'Yes')->count(),
                'winners' => DB::table('winners')->when($venue !== 'all', fn ($q) => $q->where('winners_venue', $venue))->count(),
            ],
            'series' => $series,
            'seriesRange' => $start->format('j M') . ' – ' . $end->format('j M Y'),
            'countries' => $scope()->select('country', DB::raw('COUNT(*) as n'))->whereNotNull('country')->groupBy('country')->orderByDesc('n')->get(),
            'professions' => $scope()->select('profession', DB::raw('COUNT(*) as n'))->whereNotNull('profession')->where('profession', '!=', '')
                ->groupBy('profession')->orderByDesc('n')->limit(6)->get(),
            // Who is attending: gender split and every profession (incl. ones with no registrations yet)
            'genders' => $scope()->select('gender', DB::raw('COUNT(*) as n'))->groupBy('gender')->pluck('n', 'gender'),
            'professionCards' => (function () use ($scope) {
                $counts = $scope()->select('profession', DB::raw('COUNT(*) as n'))->whereNotNull('profession')->where('profession', '!=', '')->groupBy('profession')->pluck('n', 'profession');
                $names = \App\Models\FormOption::professions()->pluck('label');
                foreach ($counts->keys() as $p) if (!$names->contains($p)) $names->push($p); // older values not in the list any more
                return $names->unique()->map(fn ($p) => ['name' => $p, 'n' => (int) ($counts[$p] ?? 0)])->sortByDesc('n')->values();
            })(),
            'recent' => $canList ? $scope()->orderByDesc('id')->limit(6)->get() : collect(),
            'data' => $canList ? $scope()->orderByDesc('id')->get() : collect(),
        ];
    }
    public function logout() {
        Session::flush();
        Auth::logout();
        return redirect()->route('login')->with('success', 'Successfully Logout !');
    }
}
