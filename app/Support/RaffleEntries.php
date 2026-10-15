<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The raffle "my list" of an event: registration numbers that may win
 * (Raffle setup > Who can win > Only the numbers on my list).
 *
 * Numbers can be typed one by one, pasted (any separator), or imported from Excel (.xlsx) / CSV.
 * In a file with a header row, a column named like "registration number", "number", "code" or "pin"
 * is used; otherwise every cell that looks like a registration number (4–10 digits).
 */
class RaffleEntries
{
    public const HEADER_WORDS = ['registration', 'reg', 'number', 'no', 'code', 'pin', 'qr', 'ticket', 'coupon'];

    /**
     * Add numbers (plain numbers, or ['number' => .., 'name' => .., 'email' => .., 'country' => ..] from an import).
     * Returns [added, already in the list, not registered for this event].
     */
    public static function add(Event $event, array $numbers, string $source, ?int $userId): array
    {
        $rows = [];
        foreach ($numbers as $n) {
            $row = is_array($n) ? $n : ['number' => $n];
            $num = preg_replace('/\D/', '', (string) ($row['number'] ?? ''));
            if (strlen($num) < 4 || strlen($num) > 10 || isset($rows[$num])) continue;
            $email = trim((string) ($row['email'] ?? ''));
            $rows[$num] = ['number' => $num, 'name' => mb_substr(trim((string) ($row['name'] ?? '')), 0, 150) ?: null,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_substr($email, 0, 190) : null,
                'country' => mb_substr(trim((string) ($row['country'] ?? '')), 0, 100) ?: null];
        }
        $numbers = array_keys($rows);
        $numbers = array_map('strval', $numbers);
        if (!$numbers) return [0, 0, 0];
        $existing = DB::table('raffle_entries')->where('event_id', $event->id)->whereIn('number', $numbers)->pluck('number')->map(fn ($n) => (string) $n)->all();
        $new = array_values(array_diff($numbers, $existing));
        $now = now();
        foreach (array_chunk($new, 500) as $chunk) {
            DB::table('raffle_entries')->insert(array_map(fn ($n) => $rows[$n] + ['event_id' => $event->id, 'source' => $source, 'added_by' => $userId, 'created_at' => $now, 'updated_at' => $now], $chunk));
        }
        // Re-importing a file fills in names / emails that were missing
        foreach ($existing as $n) {
            $fill = array_filter(['name' => $rows[$n]['name'], 'email' => $rows[$n]['email'], 'country' => $rows[$n]['country']]);
            if ($fill) DB::table('raffle_entries')->where('event_id', $event->id)->where('number', $n)->update($fill + ['updated_at' => $now]);
        }
        $registered = static::registeredNumbers($event, $new);
        return [count($new), count($existing), count($new) - count($registered)];
    }

    /** Numbers in a pasted / typed text (anything that is not a digit separates them). */
    public static function fromText(string $text): array
    {
        preg_match_all('/\d{4,10}/', $text, $m);
        return $m[0];
    }

    /** Numbers in an uploaded .xlsx / .csv / .txt file. */
    public static function fromFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $rows = $ext === 'xlsx' ? static::readXlsx($file->getRealPath()) : static::readCsv($file->getRealPath());
        return static::numbersFromRows($rows);
    }

    /** List with who each number belongs to (in this event). */
    public static function list(Event $event): array
    {
        return DB::table('raffle_entries as e')
            ->leftJoin('registrations as r', function ($j) use ($event) {
                $j->on('r.qr_code', '=', 'e.number')->where(fn ($q) => $q->where('r.event_id', $event->id)->orWhere('r.venue', $event->venue_code));
            })
            ->leftJoin('winners as w', fn ($j) => $j->on('w.register_id', '=', 'r.id')->where('w.event_id', $event->id))
            ->where('e.event_id', $event->id)
            ->orderByDesc('e.id')
            ->leftJoin('winners as we', fn ($j) => $j->on('we.entry_id', '=', 'e.id'))
            ->get(['e.id', 'e.number', 'e.source', 'e.name as entry_name', 'e.email as entry_email', 'e.country as entry_country',
                'r.id as registration_id', 'r.title', 'r.firstname', 'r.lastname', 'r.country', 'r.verify_status', 'w.id as winner_id', 'we.id as entry_winner_id'])
            ->map(fn ($e) => [
                'id' => $e->id, 'number' => $e->number, 'source' => $e->source, 'registered' => (bool) $e->registration_id,
                'name' => $e->registration_id ? trim($e->title . ' ' . $e->firstname . ' ' . $e->lastname) : $e->entry_name,
                'email' => $e->entry_email,
                'country' => $e->country ?: $e->entry_country, 'checked_in' => $e->verify_status === 'Yes', 'won' => (bool) ($e->winner_id || $e->entry_winner_id),
            ])->values()->all();
    }

    public static function counts(Event $event): array
    {
        $total = DB::table('raffle_entries')->where('event_id', $event->id)->count();
        $matched = count(static::registeredNumbers($event, DB::table('raffle_entries')->where('event_id', $event->id)->pluck('number')->all()));
        return ['total' => $total, 'registered' => $matched, 'unknown' => $total - $matched];
    }

    private static function registeredNumbers(Event $event, array $numbers): array
    {
        if (!$numbers) return [];
        return DB::table('registrations')->whereIn('qr_code', $numbers)
            ->where(fn ($q) => $q->where('event_id', $event->id)->orWhere('venue', $event->venue_code))
            ->distinct()->pluck('qr_code')->map(fn ($n) => (string) $n)->all();
    }

    /**
     * Pick the number column if the file has a header row (plus Name / Email / Country columns, if present);
     * without a header, every cell that looks like a number.
     */
    private static function numbersFromRows(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => array_filter($r, fn ($c) => trim((string) $c) !== '')));
        if (!$rows) return [];
        $column = null;
        $best = 0;
        $extra = [];
        // The number column: "Registration / Ticket / Code / PIN / QR" beats a plain "Number / No"; phone columns never count
        foreach ($rows[0] as $i => $cell) {
            $words = preg_split('/[^a-z]+/', strtolower((string) $cell), -1, PREG_SPLIT_NO_EMPTY);
            if (preg_match('/\d{4,}/', (string) $cell) || array_intersect($words, ['phone', 'mobile', 'tel', 'telephone', 'contact', 'whatsapp'])) continue;
            $score = array_intersect($words, ['registration', 'reg', 'ticket', 'code', 'pin', 'qr', 'coupon']) ? 2 : (array_intersect($words, static::HEADER_WORDS) ? 1 : 0);
            if ($score > $best) { $best = $score; $column = $i; }
        }
        foreach ($rows[0] as $i => $cell) {
            $text = strtolower(trim((string) $cell));
            $words = preg_split('/[^a-z]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
            if ($i === $column || preg_match('/\d{4,}/', $text)) continue;
            if (!isset($extra['email']) && array_intersect($words, ['email', 'mail', 'e'])) $extra['email'] = $i;
            elseif (!isset($extra['country']) && array_intersect($words, ['country', 'nationality'])) $extra['country'] = $i;
            elseif (!isset($extra['name']) && array_intersect($words, ['name', 'fullname', 'participant', 'attendee', 'delegate'])) $extra['name'] = $i;
        }
        $out = [];
        foreach ($column !== null ? array_slice($rows, 1) : $rows as $row) {
            $cells = $column !== null ? [$row[$column] ?? ''] : $row;
            foreach ($cells as $cell) {
                $cell = trim((string) $cell);
                if (preg_match('/^\d+\.0+$/', $cell)) $cell = (string) (int) $cell; // Excel number stored as 716124.0
                if (!preg_match('/^\d{4,10}$/', $cell)) continue;
                $entry = ['number' => $cell];
                if ($column !== null) foreach ($extra as $k => $i) $entry[$k] = trim((string) ($row[$i] ?? ''));
                $out[] = $entry;
            }
        }
        return $out;
    }

    private static function readCsv(string $path): array
    {
        $rows = [];
        $fh = fopen($path, 'r');
        if (!$fh) return [];
        $first = fgets($fh);
        rewind($fh);
        $delimiter = $first !== false && substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($c) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $c)), $row);
            if (count($rows) > 50000) break;
        }
        fclose($fh);
        return $rows;
    }

    /** First worksheet of an .xlsx file as rows of cell values (no library needed). */
    private static function readXlsx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException('Excel import needs the PHP zip extension. Save the file as CSV and import that instead.');
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw new \RuntimeException('This file could not be opened as an Excel (.xlsx) file.');

        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            foreach ($sx->si ?? [] as $si) {
                $text = '';
                if (isset($si->t)) $text = (string) $si->t;
                foreach ($si->r ?? [] as $r) $text .= (string) $r->t;
                $strings[] = $text;
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) { $sheet = $zip->getFromIndex($i); break; }
            }
        }
        $zip->close();
        if ($sheet === false) throw new \RuntimeException('No worksheet found in this Excel file.');

        $sx = simplexml_load_string($sheet, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        $rows = [];
        foreach ($sx->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = static::columnIndex((string) $c['r']);
                $type = (string) $c['t'];
                $value = $type === 's' ? ($strings[(int) $c->v] ?? '') : ($type === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
                $cells[$col] = $value;
            }
            if ($cells) { ksort($cells); $rows[] = $cells + array_fill(0, max(array_keys($cells)) + 1, ''); ksort($rows[count($rows) - 1]); }
            if (count($rows) > 50000) break;
        }
        return $rows;
    }

    private static function columnIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
        $n = 0;
        foreach (str_split($letters ?: 'A') as $ch) $n = $n * 26 + (ord($ch) - 64);
        return $n - 1;
    }
}
