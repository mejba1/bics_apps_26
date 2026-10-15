<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\ParticipantAnalytics;
use Illuminate\Http\Request;

/** Reports › Participant analytics (permission "reports.analytics"). */
class AnalyticsController extends Controller
{
    private function analytics(Request $request): ParticipantAnalytics
    {
        $ev = $request->input('event');
        $id = $ev === 'all' ? null : ($request->filled('event') ? (int) $ev : optional(\App\Support\PinnedEvent::event())->id);
        return new ParticipantAnalytics((int) $request->input('days', 30), $id ?: null);
    }

    public function index(Request $request){
        $a = $this->analytics($request);
        return view('backend.pages.analytics', [
            'a' => $a, 'kpis' => $a->kpis(), 'series' => $a->series(), 'top' => $a->top(15),
            'orgs' => $a->by('organization', 10), 'cats' => $a->by('category', 8), 'countries' => $a->by('country', 10), 'events' => $a->events(),
            'eventList' => Event::orderByDesc('is_current')->orderByDesc('event_start')->get(['id', 'event_short', 'is_current']),
            'periods' => ParticipantAnalytics::PERIODS,
        ]);
    }

    /** CSV of one table of the page. */
    public function export(Request $request, string $kind){
        $a = $this->analytics($request);
        $period = ParticipantAnalytics::PERIODS[$a->days];
        [$head, $rows] = match ($kind) {
            'top' => [['Participant ID', 'Name', 'Organization', 'Category', 'Views', 'From link', 'From QR', 'From NFC', 'Contacts saved', 'Clicks'],
                array_map(fn ($r) => [$r['code'], $r['name'], $r['organization'], $r['category'], $r['views'], $r['link'], $r['qr'], $r['nfc'], $r['vcard'], $r['clicks']], $a->top(500))],
            'organizations' => [['Organization', 'Participants'], $a->by('organization')['all']],
            'categories' => [['Category', 'Participants'], $a->by('category')['all']],
            'countries' => [['Country', 'Participants'], $a->by('country')['all']],
            'events' => [['Event', 'Registrations', 'Participants', 'Checked in', 'Certificates', 'Prizes'],
                array_map(fn ($r) => [$r['event'], $r['registrations'], $r['participants'], $r['checked_in'], $r['certificates'], $r['prizes']], $a->events())],
            'daily' => (function () use ($a) {
                $s = $a->series();
                return [[$s['monthly'] ? 'Month' : 'Day', 'Views from link', 'Views from QR', 'Views from NFC', 'Contacts saved', 'Dashboard sign-ins'],
                    array_map(fn ($i) => [$s['keys'][$i], $s['link'][$i], $s['qr'][$i], $s['nfc'][$i], $s['vcard'][$i], $s['logins'][$i]], array_keys($s['keys']))];
            })(),
        };
        $name = 'participant-' . $kind . '-' . now()->format('Y-m-d') . '.csv';
        return response()->streamDownload(function () use ($head, $rows, $period) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $head);
            foreach ($rows as $r) fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r));   // no spreadsheet formulas
            fputcsv($out, []);
            fputcsv($out, ['Period: ' . $period . ' (visit numbers only)', 'Made ' . now()->format('j M Y H:i')]);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
