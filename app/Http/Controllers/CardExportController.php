<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;
use App\Models\Event;
use App\Models\Participant;
use App\Support\CardData;
use App\Support\CardPrint;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Card downloads for one or many participants (Card Design Studio › Previews, Card Printing):
 *   data  — designs + card data for the browser exports (PNG / JPG / EPS, see assets/backend/card-studio/card-export.js)
 *   pdf   — vector PDF (one card per page, front then back), or a ZIP with one PDF per participant
 * Permission: "cards.design" or "cards.print".
 */
class CardExportController extends Controller
{
    public const MAX = 300;

    private function allowed(Request $request): void
    {
        abort_unless($request->user()->can('cards.design') || $request->user()->can('cards.print'), 403);
    }

    /** [participant, template, event] rows for the ids, with each person's own template unless one is forced. */
    private function rows(Request $request, bool $forPrint): array
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:' . static::MAX, 'ids.*' => 'integer',
            'template_id' => 'nullable|integer|exists:card_templates,id', 'event_id' => 'nullable|integer|exists:events,id',
        ], ['ids.required' => 'Choose at least one participant.', 'ids.max' => 'Up to ' . static::MAX . ' cards per download.']);
        $event = !empty($data['event_id']) ? Event::find($data['event_id']) : null;
        $forced = !empty($data['template_id']) ? CardTemplate::find($data['template_id']) : null;
        $order = array_flip(array_map('intval', $data['ids']));
        $people = Participant::whereIn('id', $data['ids'])->get()->sortBy(fn ($p) => $order[$p->id] ?? 0);
        $rows = [];
        foreach ($people as $p) {
            $t = $forced ?: CardTemplate::forParticipant($p, $event);
            if ($t) $rows[] = [$p, $t, CardData::for($p, $event, $forPrint)];
        }
        return $rows;
    }

    public function data(Request $request){
        $this->allowed($request);
        $rows = $this->rows($request, false);
        $templates = [];
        $items = [];
        foreach ($rows as [$p, $t, $d]) {
            $templates[$t->id] = ['id' => $t->id, 'name' => $t->name, 'design' => $t->design];
            $items[] = ['id' => $p->id, 'name' => $p->name, 'code' => $p->code, 'template_id' => $t->id, 'data' => $d];
        }
        return response()->json(['items' => $items, 'templates' => array_values($templates), 'skipped' => count($request->input('ids', [])) - count($items)]);
    }

    public function pdf(Request $request){
        $this->allowed($request);
        $request->validate(['sides' => 'nullable|in:both,front,back', 'bleed' => 'nullable|boolean', 'marks' => 'nullable|boolean', 'pack' => 'nullable|in:one,zip']);
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');
        $rows = $this->rows($request, true);
        if (!$rows) return response()->json(['message' => 'None of these participants has a card template.'], 422);
        $s = CardPrint::settings(['layout' => 'card', 'sides' => $request->input('sides', 'both'), 'bleed' => $request->boolean('bleed'), 'marks' => $request->boolean('marks'), 'zip' => false]);
        $stamp = now()->format('Ymd-His');
        if (count($rows) === 1 || $request->input('pack', 'one') === 'one') {
            [$pdf] = CardPrint::cards($rows, $s);
            $name = count($rows) === 1 ? Str::slug($rows[0][0]->code . '-' . $rows[0][0]->name) . '.pdf' : 'cards-' . count($rows) . '-' . $stamp . '.pdf';
            $pdf->SetTitle(count($rows) === 1 ? 'Card — ' . $rows[0][0]->name : count($rows) . ' cards');
            return response($pdf->Output('', 'S'), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $name . '"']);
        }
        abort_unless(class_exists(\ZipArchive::class), 500, 'The PHP zip extension is missing on this server.');
        $tmp = tempnam(sys_get_temp_dir(), 'cards');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($rows as $row) {
            [$single] = CardPrint::cards([$row], $s);
            $zip->addFromString(Str::slug($row[0]->code . '-' . $row[0]->name) . '.pdf', $single->Output('', 'S'));
        }
        $zip->close();
        return response()->download($tmp, 'cards-pdf-' . count($rows) . '-' . $stamp . '.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }
}
