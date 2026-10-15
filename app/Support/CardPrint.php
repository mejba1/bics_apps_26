<?php

namespace App\Support;

use App\Models\CardPrintJob;
use App\Models\CardTemplate;
use App\Models\Event;
use App\Models\NfcCard;
use App\Models\Participant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk card printing: who is in a batch, checks before printing, and the print files
 * (vector PDF — one card per page for card printers or N-up sheets with crop marks — a ZIP of one PDF per
 * participant and a CSV list that maps every card / page to its participant and NFC link).
 */
class CardPrint
{
    public const MAX = 1000;
    public const PAPERS = ['a4' => ['A4 (210 × 297 mm)', 210, 297], 'a3' => ['A3 (297 × 420 mm)', 297, 420], 'letter' => ['Letter (216 × 279 mm)', 215.9, 279.4],
        'sra3' => ['SRA3 (320 × 450 mm)', 320, 450], 'custom' => ['Custom size', 0, 0]];
    public const DEFAULTS = ['layout' => 'card', 'paper' => 'a4', 'paper_w' => 210, 'paper_h' => 297, 'sides' => 'both', 'bleed' => true, 'marks' => true,
        'gap' => 6, 'margin' => 8, 'flip' => 'long', 'zip' => false];

    /* ---------------------------------------------------------------- who */

    /**
     * Participants for a batch. $f: event_id, category, scope (all | not_printed | with_card | no_card), ids (picked list — then
     * only those, in that order).
     */
    public static function select(array $f): Collection
    {
        $q = Participant::where('status', 'active');
        if (!empty($f['ids'])) {
            $ids = array_values(array_unique(array_map('intval', $f['ids'])));
            $found = $q->whereIn('id', $ids)->get()->keyBy('id');
            return collect($ids)->map(fn ($id) => $found[$id] ?? null)->filter()->take(static::MAX)->values();
        }
        if (!empty($f['event_id']) && ($event = Event::find($f['event_id']))) {
            $q->whereIn('id', DB::table('registrations')->whereNotNull('participant_id')
                ->where(fn ($w) => $w->where('event_id', $event->id)->orWhere(fn ($v) => $v->whereNull('event_id')->where('venue', $event->venue_code)))
                ->select('participant_id'));
        }
        if (!empty($f['category'])) $q->where('category', $f['category']);
        $scope = $f['scope'] ?? 'all';
        if ($scope === 'not_printed') $q->whereNotIn('id', DB::table('card_print_job_items')->whereNotNull('printed_at')->select('participant_id'));
        if ($scope === 'with_card') $q->whereIn('id', NfcCard::whereIn('status', NfcCard::IN_USE)->whereNotNull('participant_id')->select('participant_id'));
        if ($scope === 'no_card') $q->whereNotIn('id', NfcCard::whereIn('status', NfcCard::IN_USE)->whereNotNull('participant_id')->select('participant_id'));
        return $q->orderBy('sequence')->limit(static::MAX + 1)->get();
    }

    /* ---------------------------------------------------------------- checks */

    /**
     * One row per participant: the template they get, their data and what may be wrong.
     * issue levels: error (cannot print), warn (prints, but check it), info.
     */
    public static function check(Collection $people, ?CardTemplate $forced, ?Event $event): array
    {
        $pdf = CardPdf::newPdf(100, 100);   // only to measure text
        $printed = DB::table('card_print_job_items')->whereIn('participant_id', $people->pluck('id'))->whereNotNull('printed_at')
            ->select('participant_id', DB::raw('MAX(printed_at) as at'), DB::raw('COUNT(*) as n'))->groupBy('participant_id')->get()->keyBy('participant_id');
        $rows = [];
        $seenEmail = $seenName = [];
        foreach ($people as $p) {
            $t = $forced ?: CardTemplate::forParticipant($p, $event);
            $data = CardData::for($p, $event, true);
            $issues = [];
            if (!$t) $issues[] = ['error', 'No card template'];
            else {
                $d = $t->design;
                $sides = $d['double_sided'] ? ['front', 'back'] : ['front'];
                $empty = $cut = [];
                $photo = $nfcQr = false;
                foreach ($sides as $side) foreach ($d[$side]['elements'] as $e) {
                    if (!empty($e['hidden'])) continue;
                    if ($e['type'] === 'text') {
                        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $e['text'], $m);
                        foreach ($m[1] as $k) if (trim((string) ($data['values'][$k] ?? '')) === '') $empty[$k] = CardTemplate::PLACEHOLDERS[$k] ?? $k;
                        $text = CardPdf::textOf($e, $data);
                        if ($text !== '') {
                            $font = CardFonts::use($pdf, $e['font'], (int) $e['weight'], !empty($e['italic']));
                            if (CardPdf::layout($pdf, $e, $font, $text)['cut']) $cut[] = $e['name'];
                        }
                    }
                    if ($e['type'] === 'image' && $e['source'] === 'photo') $photo = true;
                    if ($e['type'] === 'image' && $e['source'] === 'logo' && empty($data['logo'])) $empty['logo'] = 'Organization logo';
                    if ($e['type'] === 'qr' && $e['source'] === 'nfc') $nfcQr = true;
                }
                if ($photo && empty($data['photo'])) $issues[] = ['warn', 'No photo — initials will print'];
                if ($empty) $issues[] = ['warn', 'Empty: ' . implode(', ', $empty)];
                if ($cut) $issues[] = ['warn', 'Text too long, shortened with "…": ' . implode(', ', array_unique($cut))];
                if ($nfcQr && !$data['values']['card_no']) $issues[] = ['info', 'No NFC card — the QR code uses the profile link'];
            }
            $email = mb_strtolower(trim((string) $p->email));
            $nameKey = mb_strtolower(preg_replace('/\s+/', ' ', trim($p->first_name . ' ' . $p->last_name . '|' . $p->organization)));
            if ($email && isset($seenEmail[$email])) $issues[] = ['warn', 'Same email as ' . $seenEmail[$email]];
            elseif (isset($seenName[$nameKey])) $issues[] = ['warn', 'Same name and organization as ' . $seenName[$nameKey]];
            if ($email) $seenEmail[$email] = $p->code;
            $seenName[$nameKey] = $p->code;
            if (isset($printed[$p->id])) $issues[] = ['info', 'Printed before (' . \Carbon\Carbon::parse($printed[$p->id]->at)->format('j M Y') . ($printed[$p->id]->n > 1 ? ', ' . $printed[$p->id]->n . ' times' : '') . ')'];
            $rows[] = [
                'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'organization' => (string) $p->organization, 'category' => (string) $p->category,
                'template_id' => $t?->id, 'template' => $t?->name, 'card_no' => $data['values']['card_no'] ?: null,
                'issues' => $issues, 'level' => collect($issues)->contains(fn ($i) => $i[0] === 'error') ? 'error' : (collect($issues)->contains(fn ($i) => $i[0] === 'warn') ? 'warn' : 'ok'),
            ];
        }
        return $rows;
    }

    /* ---------------------------------------------------------------- settings */

    public static function settings(array $in): array
    {
        $s = array_merge(static::DEFAULTS, array_intersect_key($in, static::DEFAULTS));
        $s['layout'] = $s['layout'] === 'sheet' ? 'sheet' : 'card';
        $s['paper'] = array_key_exists($s['paper'], static::PAPERS) ? $s['paper'] : 'a4';
        if ($s['paper'] !== 'custom') { $s['paper_w'] = static::PAPERS[$s['paper']][1]; $s['paper_h'] = static::PAPERS[$s['paper']][2]; }
        $s['paper_w'] = round(max(50, min(1000, (float) $s['paper_w'])), 1);
        $s['paper_h'] = round(max(50, min(1000, (float) $s['paper_h'])), 1);
        $s['sides'] = in_array($s['sides'], ['both', 'front', 'back'], true) ? $s['sides'] : 'both';
        $s['gap'] = round(max(0, min(30, (float) $s['gap'])), 1);
        $s['margin'] = round(max(0, min(40, (float) $s['margin'])), 1);
        $s['flip'] = $s['flip'] === 'short' ? 'short' : 'long';
        foreach (['bleed', 'marks', 'zip'] as $k) $s[$k] = filter_var($s[$k], FILTER_VALIDATE_BOOLEAN);
        return $s;
    }

    /**
     * How cards fit on a sheet: [cols, rows, landscape paper?, cell w, cell h] for the largest card of the batch.
     * (Cells are the card plus bleed; gap is the space between cells.)
     */
    public static function grid(array $s, float $cw, float $ch): array
    {
        $best = [0, 0, false];
        foreach ([false, true] as $land) {
            $pw = $land ? $s['paper_h'] : $s['paper_w'];
            $ph = $land ? $s['paper_w'] : $s['paper_h'];
            $cols = (int) floor(($pw - 2 * $s['margin'] + $s['gap']) / ($cw + $s['gap']));
            $rows = (int) floor(($ph - 2 * $s['margin'] + $s['gap']) / ($ch + $s['gap']));
            if ($cols * $rows > $best[0] * $best[1]) $best = [$cols, $rows, $land];
        }
        return [$best[0], $best[1], $best[2], $cw, $ch];
    }

    /* ---------------------------------------------------------------- files */

    /**
     * Make the files for a job. $rows: [participant, template, data] in print order.
     * Returns [files, pages, notes].
     */
    public static function make(CardPrintJob $job, array $rows, array $s): array
    {
        @set_time_limit(900);
        @ini_set('memory_limit', '1024M');
        CardFonts::$missing = [];
        $dir = $job->dir();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $slug = Str::slug($job->name ?: 'cards') ?: 'cards';
        $base = 'job-' . $job->id . '-' . $slug;
        $files = [];
        $notes = [];

        // main PDF
        [$pdf, $pages, $map] = $s['layout'] === 'sheet' ? static::sheets($rows, $s, $notes) : static::cards($rows, $s);
        $pdf->SetTitle(($job->name ?: 'Cards') . ' — print job ' . $job->id);
        $pdf->Output($dir . $base . '.pdf', 'F');
        $files['pdf'] = $base . '.pdf';

        // list (CSV): page / position of each participant, details and NFC link
        $fh = fopen($dir . $base . '.csv', 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['#', 'Front page', 'Back page', 'Position on sheet', 'Participant ID', 'Name', 'Designation', 'Organization', 'Category', 'Email',
            'NFC card', 'NFC link (write to chip)', 'Profile link (QR)', 'Template', 'File in ZIP']);
        foreach ($rows as $i => [$p, $t, $data]) {
            $m = $map[$i] ?? [];
            fputcsv($fh, [$i + 1, $m['front'] ?? '', $m['back'] ?? '', $m['slot'] ?? '', $p->code, $p->name, $p->designation, $p->organization, $p->category, $p->email,
                $data['values']['card_no'], $data['values']['card_no'] ? $data['qr']['nfc'] : '', $data['qr']['profile'], $t->name . ' (v' . $t->version . ')',
                $s['zip'] ? static::zipName($i, $p) : '']);
        }
        fclose($fh);
        $files['csv'] = $base . '.csv';

        // one PDF per participant (always one card per page, both sides as chosen)
        if ($s['zip'] && class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            $zip->open($dir . $base . '.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $one = array_merge($s, ['layout' => 'card']);
            foreach ($rows as $i => $row) {
                [$single] = static::cards([$row], $one);
                $zip->addFromString(static::zipName($i, $row[0]), $single->Output('', 'S'));
            }
            $zip->addFile($dir . $base . '.csv', 'list.csv');
            $zip->close();
            $files['zip'] = $base . '.zip';
        } elseif ($s['zip']) $notes[] = 'ZIP not made: the PHP zip extension is missing on this server.';

        if (CardFonts::$missing) $notes[] = 'Fonts not available, printed with DejaVu Sans instead: ' . implode(', ', array_keys(CardFonts::$missing)) . '.';
        return [$files, $pages, $notes];
    }

    private static function zipName(int $i, Participant $p): string
    {
        return str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT) . '-' . $p->code . '-' . (Str::slug($p->first_name . ' ' . $p->last_name) ?: 'card') . '.pdf';
    }

    private static function sides(array $s, array $design): array
    {
        $sides = $s['sides'] === 'both' ? ['front', 'back'] : [$s['sides']];
        if (empty($design['double_sided'])) $sides = array_values(array_diff($sides, ['back'])) ?: [];
        return $sides;
    }

    /** One card per page (card printers, print shops): front, back, next card… Page = card + bleed (+ room for crop marks). */
    public static function cards(array $rows, array $s): array
    {
        $pdf = null;
        $pages = 0;
        $map = [];
        foreach ($rows as $i => [$p, $t, $data]) {
            $d = $t->design;
            $b = $s['bleed'] ? (float) $d['bleed'] : 0;
            $m = $s['marks'] ? 7 : 0;
            $pw = $d['width'] + 2 * ($b + $m);
            $ph = $d['height'] + 2 * ($b + $m);
            $pdf ??= CardPdf::newPdf($pw, $ph);
            foreach (static::sides($s, $d) as $side) {
                $pdf->AddPage($pw > $ph ? 'L' : 'P', [$pw, $ph]);
                $pages++;
                $map[$i][$side] = $pages;
                CardPdf::draw($pdf, $d, $side, $data, $b + $m, $b + $m, $s['bleed']);
                if ($s['marks']) CardPdf::cropMarks($pdf, $b + $m, $b + $m, $d['width'], $d['height'], $b, 4);
            }
        }
        return [$pdf ?? CardPdf::newPdf(85.6, 54), $pages, $map];
    }

    /**
     * Sheets of N cards. Backs follow each front sheet, placed for duplex printing: flipped on the long edge the columns
     * swap; on the short edge the rows swap and the backs turn 180°.
     */
    private static function sheets(array $rows, array $s, array &$notes): array
    {
        $maxB = $s['bleed'] ? max(array_map(fn ($r) => (float) $r[1]->design['bleed'], $rows)) : 0;
        $cw = max(array_map(fn ($r) => (float) $r[1]->design['width'], $rows)) + 2 * $maxB;
        $ch = max(array_map(fn ($r) => (float) $r[1]->design['height'], $rows)) + 2 * $maxB;
        [$cols, $rowsN, $land] = static::grid($s, $cw, $ch);
        if ($cols < 1 || $rowsN < 1) {
            $notes[] = 'The card does not fit on the paper — made one card per page instead.';
            return static::cards($rows, $s);
        }
        $pw = $land ? $s['paper_h'] : $s['paper_w'];
        $ph = $land ? $s['paper_w'] : $s['paper_h'];
        $gridW = $cols * $cw + ($cols - 1) * $s['gap'];
        $gridH = $rowsN * $ch + ($rowsN - 1) * $s['gap'];
        $x0 = ($pw - $gridW) / 2;   // centred, so fronts and backs line up when printed on both sides
        $y0 = ($ph - $gridH) / 2;
        $per = $cols * $rowsN;
        $markLen = min(4, $s['gap'] - 1.5);
        if ($s['marks'] && $markLen < 1) $notes[] = 'No crop marks: the gap between cards is too small (use 3 mm or more).';
        $pdf = CardPdf::newPdf($pw, $ph);
        $pages = 0;
        $map = [];
        $anyBack = $s['sides'] !== 'front' && collect($rows)->contains(fn ($r) => !empty($r[1]->design['double_sided']));
        $faces = $s['sides'] === 'both' ? ['front', 'back'] : [$s['sides']];
        foreach (array_chunk($rows, $per, true) as $chunk) {
            foreach ($faces as $face) {
                if ($face === 'back' && !$anyBack) continue;
                $pdf->AddPage($land ? 'L' : 'P', [$pw, $ph]);
                $pages++;
                $slot = 0;
                foreach ($chunk as $i => [$p, $t, $data]) {
                    $d = $t->design;
                    $r = intdiv($slot, $cols);
                    $c = $slot % $cols;
                    $slot++;
                    $turn = false;
                    if ($face === 'back' && $s['sides'] === 'both') {
                        if ($s['flip'] === 'long') $c = $cols - 1 - $c;
                        else { $r = $rowsN - 1 - $r; $turn = true; }
                    }
                    $map[$i][$face] = $pages;
                    $map[$i]['slot'] = 'row ' . (intdiv($slot - 1, $cols) + 1) . ', column ' . (($slot - 1) % $cols + 1);
                    if ($face === 'back' && empty($d['double_sided'])) continue;
                    // card centred in its cell (cards of a smaller size in a mixed batch)
                    $b = $s['bleed'] ? (float) $d['bleed'] : 0;
                    $ox = $x0 + $c * ($cw + $s['gap']) + ($cw - $d['width']) / 2;
                    $oy = $y0 + $r * ($ch + $s['gap']) + ($ch - $d['height']) / 2;
                    if ($turn) { $pdf->StartTransform(); $pdf->Rotate(180, $ox + $d['width'] / 2, $oy + $d['height'] / 2); }
                    CardPdf::draw($pdf, $d, $face, $data, $ox, $oy, $s['bleed']);
                    if ($turn) $pdf->StopTransform();
                    // marks stay inside the gap, 0.5 mm clear of the neighbouring card's bleed
                    if ($s['marks'] && $markLen >= 1) CardPdf::cropMarks($pdf, $ox, $oy, $d['width'], $d['height'], $b, $markLen);
                }
            }
        }
        return [$pdf, $pages, $map];
    }
}
