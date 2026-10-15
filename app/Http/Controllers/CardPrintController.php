<?php

namespace App\Http\Controllers;

use App\Models\CardPrintJob;
use App\Models\CardTemplate;
use App\Models\Event;
use App\Models\NfcCard;
use App\Models\Participant;
use App\Support\CardData;
use App\Support\CardPrint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin > Card printing (permission "cards.print"): choose participants, check them, make print files
 * (vector PDF, CSV list, ZIP of single cards), keep the history, mark batches printed and reprint.
 */
class CardPrintController extends Controller
{
    public function index(){
        return view('backend.pages.card-print.index', [
            'events' => Event::orderByDesc('is_current')->orderByDesc('event_start')->orderByDesc('id')->get(['id', 'event_short', 'is_current']),
            'pinnedId' => optional(\App\Support\PinnedEvent::event())->id,
            'categories' => Participant::whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->limit(100)->pluck('category'),
            'templates' => CardTemplate::orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']),
            'papers' => CardPrint::PAPERS,
            'defaults' => CardPrint::DEFAULTS,
            'jobs' => $this->jobs(),
            'icons' => json_decode((string) file_get_contents(base_path('assets/backend/card-studio/icons.json')), true),
            'zip' => class_exists(\ZipArchive::class),
        ]);
    }

    /** Who would be printed, with the checks. */
    public function check(Request $request){
        [$people, $template, $event] = $this->who($request);
        if ($people->count() > CardPrint::MAX) {
            return response()->json(['success' => false, 'message' => 'More than ' . CardPrint::MAX . ' participants match. Choose an event, a category or "not printed yet" to make smaller batches.'], 422);
        }
        $rows = CardPrint::check($people, $template, $event);
        $ids = collect($rows)->pluck('template_id')->filter()->unique();
        return response()->json([
            'rows' => $rows,
            'templates' => CardTemplate::whereIn('id', $ids)->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'version' => $t->version, 'design' => $t->design])->keyBy('id'),
        ]);
    }

    /** Make the print files for the participants ticked after the check. */
    public function store(Request $request){
        $request->validate([
            'ids' => 'required|array|min:1|max:' . CardPrint::MAX,
            'ids.*' => 'integer',
            'name' => 'nullable|string|max:120',
            'settings' => 'required|array',
        ], ['ids.required' => 'Tick at least one participant.']);
        [, $forced, $event] = $this->who($request, false);
        $s = CardPrint::settings($request->input('settings'));
        $people = CardPrint::select(['ids' => $request->input('ids')]);
        $rows = [];
        foreach ($people as $p) {
            $t = $forced ?: CardTemplate::forParticipant($p, $event);
            if ($t) $rows[] = [$p, $t, CardData::for($p, $event, true)];
        }
        if (!$rows) return response()->json(['success' => false, 'message' => 'None of these participants can be printed (no template).'], 422);

        $job = new CardPrintJob(['name' => $request->input('name') ? Str::limit(trim($request->input('name')), 120, '') : null, 'event_id' => $event?->id,
            'template_id' => $forced?->id, 'settings' => $s, 'participant_count' => count($rows), 'status' => 'ready']);
        $job->created_by = auth()->id();
        $job->save();
        $cards = NfcCard::whereIn('participant_id', collect($rows)->map(fn ($r) => $r[0]->id))->whereIn('status', NfcCard::IN_USE)->pluck('id', 'participant_id');
        DB::table('card_print_job_items')->insert(collect($rows)->map(fn ($r, $i) => [
            'job_id' => $job->id, 'participant_id' => $r[0]->id, 'template_id' => $r[1]->id, 'template_version' => $r[1]->version,
            'nfc_card_id' => $cards[$r[0]->id] ?? null, 'position' => $i + 1,
        ])->all());
        try {
            [$files, $pages, $notes] = CardPrint::make($job, $rows, $s);
            $job->fill(['files' => $files, 'page_count' => $pages, 'notes' => $notes ? implode("\n", $notes) : null])->save();
        } catch (\Throwable $e) {
            report($e);
            $job->fill(['status' => 'failed', 'notes' => 'The files could not be made: ' . Str::limit($e->getMessage(), 300)])->save();
            return response()->json(['success' => false, 'message' => $job->notes, 'jobs' => $this->jobs()], 500);
        }
        return response()->json(['success' => true, 'message' => count($rows) . ' cards ready (' . $pages . ' pages).', 'job' => $this->jobRow($job->fresh()), 'jobs' => $this->jobs()]);
    }

    public function download(CardPrintJob $job, string $kind){
        $file = $job->file($kind);
        abort_unless($file, 404);
        $types = ['pdf' => 'application/pdf', 'csv' => 'text/csv; charset=UTF-8', 'zip' => 'application/zip'];
        return $kind === 'pdf' && request()->boolean('inline')
            ? response()->file($file, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . basename($file) . '"'])
            : response()->download($file, basename($file), ['Content-Type' => $types[$kind]]);
    }

    /** Mark the batch printed (or not): the participants count as printed, NFC cards in the batch too. */
    public function printed(Request $request, CardPrintJob $job){
        $on = $request->boolean('printed');
        DB::transaction(function () use ($job, $on) {
            $job->printed_at = $on ? now() : null;
            $job->printed_by = $on ? auth()->id() : null;
            $job->save();
            DB::table('card_print_job_items')->where('job_id', $job->id)->update(['printed_at' => $on ? now() : null]);
            foreach (NfcCard::whereIn('id', DB::table('card_print_job_items')->where('job_id', $job->id)->whereNotNull('nfc_card_id')->pluck('nfc_card_id'))->get() as $card) {
                if (($card->print_status === 'printed') !== $on) $card->setPrinted($on, auth()->id());
            }
        });
        return response()->json(['success' => true, 'message' => $on ? 'Marked as printed.' : 'Marked as not printed.', 'jobs' => $this->jobs()]);
    }

    /** The participants of an earlier batch (to check and print again). */
    public function participants(CardPrintJob $job){
        return response()->json(['ids' => $job->items()->pluck('participant_id'), 'settings' => $job->settings, 'event_id' => $job->event_id,
            'template_id' => $job->template_id, 'name' => $job->name ? Str::limit('Reprint: ' . $job->name, 120, '') : 'Reprint of job ' . $job->id]);
    }

    public function destroy(CardPrintJob $job){
        foreach (glob($job->dir() . '*') ?: [] as $f) @unlink($f);
        @rmdir($job->dir());
        $job->delete();
        return response()->json(['success' => true, 'message' => 'Print job deleted.', 'jobs' => $this->jobs()]);
    }

    /** Participant search for picking people by hand. */
    public function search(Request $request){
        $s = trim((string) $request->input('q'));
        if (mb_strlen($s) < 2) return response()->json([]);
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
        return response()->json(Participant::where('status', 'active')
            ->where(fn ($w) => $w->where('code', 'like', $like)->orWhere(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'like', $like)->orWhere('email', 'like', $like)->orWhere('organization', 'like', $like))
            ->orderBy('first_name')->limit(15)->get()->map(fn ($p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'organization' => $p->organization]));
    }

    /** [participants, forced template, event] from the form. */
    private function who(Request $request, bool $people = true): array
    {
        $request->validate([
            'event_id' => 'nullable|integer|exists:events,id',
            'template_id' => 'nullable|integer|exists:card_templates,id',
            'category' => 'nullable|string|max:100',
            'scope' => ['nullable', Rule::in(['all', 'not_printed', 'with_card', 'no_card'])],
            'picked' => 'nullable|array|max:' . CardPrint::MAX,
            'picked.*' => 'integer',
        ]);
        $event = $request->filled('event_id') ? Event::find($request->input('event_id')) : null;
        $template = $request->filled('template_id') ? CardTemplate::find($request->input('template_id')) : null;
        $list = $people ? CardPrint::select(['event_id' => $event?->id, 'category' => $request->input('category'), 'scope' => $request->input('scope', 'all'),
            'ids' => $request->input('picked') ?: null]) : collect();
        return [$list, $template, $event];
    }

    private function jobs(){
        return CardPrintJob::with(['event:id,event_short', 'template:id,name', 'creator:id,name'])->orderByDesc('id')->limit(50)->get()->map(fn ($j) => $this->jobRow($j));
    }

    private function jobRow(CardPrintJob $j): array
    {
        $s = $j->settings;
        $layout = $s['layout'] === 'sheet' ? 'Sheets · ' . (CardPrint::PAPERS[$s['paper']][0] ?? '') : 'One card per page';
        $layout = preg_replace('/ \(.*\)$/', '', $layout);
        $extras = array_filter([['both' => 'front + back', 'front' => 'front only', 'back' => 'back only'][$s['sides']] ?? '', $s['bleed'] ? 'bleed' : '', $s['marks'] ? 'crop marks' : '']);
        return [
            'id' => $j->id, 'name' => $j->name ?: 'Print job ' . $j->id, 'event' => $j->event->event_short ?? null, 'event_id' => $j->event_id, 'template' => $j->template->name ?? 'Each participant\'s own',
            'count' => $j->participant_count, 'pages' => $j->page_count, 'layout' => $layout . ' · ' . implode(', ', $extras), 'status' => $j->status, 'notes' => $j->notes,
            'at' => $j->created_at?->format('j M Y, H:i'), 'by' => $j->creator->name ?? null, 'printed' => $j->printed_at?->format('j M Y'),
            'files' => collect(['pdf', 'csv', 'zip'])->filter(fn ($k) => $j->file($k))->mapWithKeys(fn ($k) => [$k => route('card-print.download', [$j, $k])]),
            'view' => $j->file('pdf') ? route('card-print.download', [$j, 'pdf']) . '?inline=1' : null,
        ];
    }
}
