<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registration reports (Admin > Reports).
 *  - Attendee list: every matching registration (PDF landscape, or CSV for Excel).
 *  - Summary: counts grouped by date, month, year, event, country, gender, profession… (PDF or CSV).
 * Both take the same filters: venue (null = all events), country, gender, profession, status, from, to, year.
 */
class RegistrationReport
{
    /** Group-by options: key => [label, SQL expression, sort] */
    public const GROUPS = [
        'date' => ['Date', 'DATE(created_at)', 'label'],
        'month' => ['Month', "DATE_FORMAT(created_at, '%Y-%m')", 'label'],
        'year' => ['Year', 'YEAR(created_at)', 'label'],
        'venue' => ['Event', 'venue', 'total'],
        'country' => ['Country', 'country', 'total'],
        'gender' => ['Gender', 'gender', 'total'],
        'profession' => ['Profession', 'profession', 'total'],
        'specialty' => ['Speciality', 'specialty', 'total'],
        'status' => ['Check-in', "CASE WHEN verify_status = 'Yes' THEN 'Checked in' ELSE 'Not checked in' END", 'total'],
    ];

    public const STATUSES = ['checked_in' => 'Checked in', 'not_checked_in' => 'Not checked in'];

    /** Keep only known, non-empty filters. */
    public static function filters(array $f): array
    {
        $out = [];
        foreach (['venue', 'country', 'gender', 'profession'] as $k) {
            if (isset($f[$k]) && trim((string) $f[$k]) !== '') $out[$k] = trim((string) $f[$k]);
        }
        if (isset($f['status']) && isset(static::STATUSES[$f['status']])) $out['status'] = $f['status'];
        foreach (['from', 'to'] as $k) {
            if (!empty($f[$k])) {
                try { $out[$k] = Carbon::parse($f[$k])->format('Y-m-d'); } catch (\Throwable $e) {}
            }
        }
        if (!empty($f['year']) && preg_match('/^\d{4}$/', (string) $f['year'])) $out['year'] = (int) $f['year'];
        return $out;
    }

    public static function query(array $f)
    {
        $f = static::filters($f);
        return DB::table('registrations')
            ->when($f['venue'] ?? null, fn ($q, $v) => $q->where('venue', $v))
            ->when($f['country'] ?? null, fn ($q, $v) => $q->where('country', $v))
            ->when($f['gender'] ?? null, fn ($q, $v) => $q->where('gender', $v))
            ->when($f['profession'] ?? null, fn ($q, $v) => $q->where('profession', $v))
            ->when(($f['status'] ?? null) === 'checked_in', fn ($q) => $q->where('verify_status', 'Yes'))
            ->when(($f['status'] ?? null) === 'not_checked_in', fn ($q) => $q->where(fn ($w) => $w->whereNull('verify_status')->orWhere('verify_status', '!=', 'Yes')))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($f['year'] ?? null, fn ($q, $v) => $q->whereYear('created_at', $v));
    }

    /** Counts per group: label, total, male, female, checked_in. */
    public static function groups(string $by, array $f): Collection
    {
        [, $expr, $sort] = static::GROUPS[$by] ?? static::GROUPS['country'];
        $rows = static::query($f)
            ->selectRaw("$expr as label, COUNT(*) as total,
                SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female,
                SUM(CASE WHEN verify_status = 'Yes' THEN 1 ELSE 0 END) as checked_in")
            ->groupByRaw($expr)
            ->get()
            ->map(function ($r) use ($by) {
                $r->total = (int) $r->total; $r->male = (int) $r->male; $r->female = (int) $r->female; $r->checked_in = (int) $r->checked_in;
                $r->key = (string) $r->label;
                $r->label = static::groupLabel($by, $r->label);
                return $r;
            });
        return $sort === 'label' ? $rows->sortBy('key')->values() : $rows->sortByDesc('total')->values();
    }

    private static function groupLabel(string $by, $value): string
    {
        if ($value === null || $value === '') return 'Not given';
        if ($by === 'date') return Carbon::parse($value)->format('D, j M Y');
        if ($by === 'month') return Carbon::createFromFormat('Y-m', $value)->format('F Y');
        return (string) $value;
    }

    /** "Event: Spain-26 · 1 Jan 2026 – 31 Mar 2026 · Female · Country: Spain" */
    public static function describe(array $f): string
    {
        $f = static::filters($f);
        $date = fn ($d) => Carbon::parse($d)->format('j M Y');
        $parts = [($f['venue'] ?? null) ? 'Event: ' . $f['venue'] : 'All events'];
        if (isset($f['from']) && isset($f['to'])) $parts[] = $date($f['from']) . ' – ' . $date($f['to']);
        elseif (isset($f['from'])) $parts[] = 'From ' . $date($f['from']);
        elseif (isset($f['to'])) $parts[] = 'Until ' . $date($f['to']);
        if (isset($f['year'])) $parts[] = 'Year ' . $f['year'];
        if (isset($f['country'])) $parts[] = 'Country: ' . $f['country'];
        if (isset($f['gender'])) $parts[] = $f['gender'];
        if (isset($f['profession'])) $parts[] = $f['profession'];
        if (isset($f['status'])) $parts[] = static::STATUSES[$f['status']];
        return implode('  ·  ', $parts);
    }

    /** Attendee list as PDF. */
    public static function download(array $filters)
    {
        $f = static::filters($filters);
        $rows = static::query($f)->orderBy('id')->get();
        $title = ['checked_in' => 'Checked-in attendees', 'not_checked_in' => 'Attendees not checked in yet'][$f['status'] ?? ''] ?? 'Registered attendees';

        $pdf = Pdf::loadView('backend.pages.report.registrations', static::common($f) + [
            'rows' => $rows,
            'title' => $title,
            'summary' => static::summary($rows),
        ])->setPaper('a4', 'landscape');

        return static::pdfResponse($pdf, static::fileName('registrations', $f) . '.pdf', $f['venue'] ?? null, 'Confidential — contains personal data');
    }

    /** Grouped summary as PDF. */
    public static function downloadSummary(string $by, array $filters)
    {
        $f = static::filters($filters);
        $by = isset(static::GROUPS[$by]) ? $by : 'country';
        $groups = static::groups($by, $f);
        $pdf = Pdf::loadView('backend.pages.report.summary', static::common($f) + [
            'title' => 'Registrations by ' . strtolower(static::GROUPS[$by][0]),
            'groupLabel' => static::GROUPS[$by][0],
            'groups' => $groups,
            'totals' => static::totals($groups),
        ])->setPaper('a4', 'portrait');

        return static::pdfResponse($pdf, static::fileName('summary-by-' . $by, $f) . '.pdf', $f['venue'] ?? null);
    }

    /** CSV (opens in Excel): attendee list, or a grouped summary when $by is given. */
    public static function csv(array $filters, ?string $by = null)
    {
        $f = static::filters($filters);
        if ($by && isset(static::GROUPS[$by])) {
            $groups = static::groups($by, $f);
            $head = [static::GROUPS[$by][0], 'Registrations', 'Male', 'Female', 'Checked in', '% of total'];
            $sum = max(1, $groups->sum('total'));
            $lines = $groups->map(fn ($g) => [$g->label, $g->total, $g->male, $g->female, $g->checked_in, round($g->total / $sum * 100, 1)]);
            $name = static::fileName('summary-by-' . $by, $f);
        } else {
            $head = ['#', 'Reg. no', 'Title', 'First name', 'Last name', 'Gender', 'Date of birth', 'Email', 'Phone', 'Country', 'Profession', 'Speciality',
                'Designation', 'Institution', 'Institution address', 'Event', 'Registered at', 'Checked in', 'Checked in at', 'Gift', 'Email sent'];
            $i = 0;
            $lines = static::query($f)->orderBy('id')->get()->map(fn ($r) => [++$i, $r->qr_code, $r->title, $r->firstname, $r->lastname, $r->gender, $r->birthday,
                $r->email, $r->phone, $r->country, $r->profession, $r->specialty, $r->designation, $r->institute, $r->institute_address, $r->venue,
                $r->created_at, $r->verify_status === 'Yes' ? 'Yes' : 'No', $r->verify_at, $r->gift, $r->email_notification === 'Yes' ? 'Yes' : 'No']);
            $name = static::fileName('registrations', $f);
        }

        return response()->streamDownload(function () use ($head, $lines) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accents correctly
            fputcsv($out, $head);
            foreach ($lines as $line) {
                // Never let a cell start a spreadsheet formula
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $line));
            }
            fclose($out);
        }, $name . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function totals(Collection $groups): array
    {
        return ['total' => $groups->sum('total'), 'male' => $groups->sum('male'), 'female' => $groups->sum('female'), 'checked_in' => $groups->sum('checked_in')];
    }

    public static function summary(Collection $rows): array
    {
        $total = $rows->count();
        return [
            'total' => $total,
            'male' => $rows->where('gender', 'Male')->count(),
            'female' => $rows->where('gender', 'Female')->count(),
            'countries' => $rows->pluck('country')->filter()->unique()->count(),
            'checked_in' => $rows->where('verify_status', 'Yes')->count(),
            'professions' => $rows->groupBy('profession')->map->count()->sortDesc()->take(4),
        ];
    }

    /** Header data shared by the PDF layouts. */
    private static function common(array $f): array
    {
        $venue = $f['venue'] ?? null;
        // The report's own event (Admin > Events)
        $event = $venue ? \App\Models\Event::forVenue($venue) : null;
        $eventLine = $event
            ? implode(' · ', array_filter([$event->event_name, $event->datesLabel(), $event->event_venue]))
            : 'Beacon International Cancer Summit — ' . ($venue ?: 'all events');
        return [
            'scope' => static::describe($f),
            'eventLine' => $eventLine,
            'generatedAt' => now(EventSettings::timezone()),
            'generatedBy' => optional(auth()->user())->name,
            'logo' => base_path('assets/frontend/images/logo.png'),
        ];
    }

    private static function pdfResponse($pdf, string $name, ?string $venue = null, ?string $note = null)
    {
        $pdf->setOption('isFontSubsettingEnabled', true); // embed only the letters used: much smaller files
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        // Footer on every page: event / venue · generated · page numbers
        PdfFooter::apply($dompdf, PdfFooter::label($venue), $note);
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    private static function fileName(string $kind, array $f): string
    {
        $parts = ['BICS', $kind, $f['venue'] ?? null, isset($f['year']) ? (string) $f['year'] : null, isset($f['country']) ? Str::slug($f['country']) : null,
            isset($f['gender']) ? Str::slug($f['gender']) : null, isset($f['profession']) ? Str::slug($f['profession']) : null,
            isset($f['status']) ? str_replace('_', '-', $f['status']) : null,
            isset($f['from']) ? 'from-' . $f['from'] : null, isset($f['to']) ? 'to-' . $f['to'] : null, now()->format('Y-m-d')];
        return implode('-', array_filter($parts, fn ($p) => $p !== null && $p !== ''));
    }
}
