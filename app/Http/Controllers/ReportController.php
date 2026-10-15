<?php

namespace App\Http\Controllers;

use App\Support\PinnedEvent;
use App\Support\RegistrationReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Reports: quick reports for the pinned event, a report builder (filters + group by)
 * with an on-screen preview, PDF / CSV downloads, and pinning an event.
 */
class ReportController extends Controller
{
    public function index(Request $request){
        $pinned = PinnedEvent::get();
        $venues = PinnedEvent::venues();
        $scope = fn () => DB::table('registrations')->where('venue', $pinned);
        $distinct = fn ($col) => DB::table('registrations')->whereNotNull($col)->where($col, '!=', '')->distinct()->orderBy($col)->pluck($col);
        return view('backend.pages.reports', [
            'pinned' => $pinned,
            'current' => PinnedEvent::current(),
            'venues' => $venues,
            'counts' => [
                'total' => $scope()->count(),
                'checked_in' => $scope()->where('verify_status', 'Yes')->count(),
                'all' => $venues->sum('total'),
            ],
            'countries' => $scope()->select('country', DB::raw('COUNT(*) as n'))->whereNotNull('country')->where('country', '!=', '')
                ->groupBy('country')->orderByDesc('n')->orderBy('country')->get(),
            'options' => [
                'countries' => $distinct('country'),
                'professions' => $distinct('profession'),
                'genders' => $distinct('gender'),
                'years' => DB::table('registrations')->selectRaw('DISTINCT YEAR(created_at) as y')->whereNotNull('created_at')->orderByDesc('y')->pluck('y'),
            ],
            'groups' => collect(RegistrationReport::GROUPS)->map(fn ($g) => $g[0]),
        ]);
    }

    /**
     * /reports/download?type=list|summary&by=<group>&format=pdf|csv&venue=pinned|all|<code>&country=&gender=&profession=&status=&from=&to=&year=
     */
    public function download(Request $request){
        $f = $this->filters($request);
        $type = $request->input('type', 'list');
        $by = $request->input('by');
        if ($request->input('format') === 'csv') {
            return RegistrationReport::csv($f, $type === 'summary' ? ($by ?: 'country') : null);
        }
        return $type === 'summary'
            ? RegistrationReport::downloadSummary($by ?: 'country', $f)
            : RegistrationReport::download($f);
    }

    /** On-screen preview of the report builder (JSON). */
    public function preview(Request $request){
        $f = $this->filters($request);
        $by = array_key_exists($request->input('by'), RegistrationReport::GROUPS) ? $request->input('by') : 'country';
        $groups = RegistrationReport::groups($by, $f);
        return response()->json([
            'scope' => RegistrationReport::describe($f),
            'by' => RegistrationReport::GROUPS[$by][0],
            'groups' => $groups->map(fn ($g) => ['label' => $g->label, 'total' => $g->total, 'male' => $g->male, 'female' => $g->female, 'checked_in' => $g->checked_in])->values(),
            'totals' => RegistrationReport::totals($groups),
        ]);
    }

    /** The attendee's ID card, always generated with the latest design (opens in the browser). */
    public function idCard(\App\Models\Registration $registration){
        return \App\Support\IdCard::pdf($registration)->stream('BICS-ID-card-' . $registration->qr_code . '.pdf');
    }

    public function pin(Request $request){
        $venue = $request->validate(['venue' => 'required|string|max:60'])['venue'];
        abort_unless(PinnedEvent::exists($venue), 422, 'Unknown event.');
        PinnedEvent::set($request->user()->id, $venue);
        $message = 'Pinned ' . $venue . '. The dashboard and reports now open on this event.';
        return $request->expectsJson()
            ? response()->json(['success' => true, 'pinned' => PinnedEvent::get(), 'message' => $message])
            : back()->with('success', $message);
    }

    /** Validated filters; venue "pinned" (default) = the user's pinned event, "all" = every event. */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'type' => 'nullable|in:list,summary',
            'by' => 'nullable|in:' . implode(',', array_keys(RegistrationReport::GROUPS)),
            'format' => 'nullable|in:pdf,csv',
            'venue' => 'nullable|string|max:60',
            'country' => 'nullable|string|max:80',
            'gender' => 'nullable|string|max:20',
            'profession' => 'nullable|string|max:100',
            'status' => 'nullable|in:checked_in,not_checked_in',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'year' => 'nullable|integer|min:2000|max:2100',
        ], ['to.after_or_equal' => 'The end date must be on or after the start date.']);
        $venue = $data['venue'] ?? 'pinned';
        $data['venue'] = $venue === 'pinned' ? PinnedEvent::get() : ($venue === 'all' ? null : $venue);
        return RegistrationReport::filters($data);
    }
}
