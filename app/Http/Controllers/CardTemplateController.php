<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;
use App\Models\Event;
use App\Models\Participant;
use App\Support\CardData;
use App\Support\CardPresets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin > Card Design Studio (permission "cards.design"): card templates (front / back, millimetres), the editor,
 * versions, which template is used for which event / category, and previews with real participants.
 * Drawing is done in the browser by assets/backend/card-studio/card-renderer.js.
 */
class CardTemplateController extends Controller
{
    public function index(){
        return view('backend.pages.card-studio.index', [
            'templates' => CardTemplate::with('event:id,event_short')->orderByDesc('is_default')->orderBy('event_id')->orderBy('name')->get(),
            'sizes' => CardPresets::SIZES,
            'presets' => collect(CardPresets::all())->map(fn ($p) => $p[0]),
            'events' => $this->events(),
            'categories' => $this->categories(),
            'sample' => CardData::sample(),
        ]);
    }

    /** New template: from a starter design, a blank size, or a copy of another template. */
    public function store(Request $request){
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'from' => ['required', Rule::in(['preset', 'blank', 'copy'])],
            'preset' => ['required_if:from,preset', 'nullable', Rule::in(array_keys(CardPresets::all()))],
            'size' => ['required_if:from,blank', 'nullable', Rule::in(array_keys(CardPresets::SIZES))],
            'copy_id' => 'required_if:from,copy|nullable|integer|exists:card_templates,id',
        ]);
        $design = match ($data['from']) {
            'preset' => CardPresets::all()[$data['preset']][1],
            'blank' => CardTemplate::blankDesign($data['size']),
            'copy' => CardTemplate::findOrFail($data['copy_id'])->design,
        };
        $t = CardTemplate::createFrom($data['name'], $design, auth()->id());
        return response()->json(['success' => true, 'message' => 'Template created.', 'edit' => route('card-studio.edit', $t)]);
    }

    public function edit(CardTemplate $template){
        return view('backend.pages.card-studio.studio', [
            'template' => $template,
            'versions' => $this->versionList($template),
            'fonts' => CardTemplate::FONTS,
            'placeholders' => CardTemplate::PLACEHOLDERS,
            'icons' => json_decode((string) file_get_contents(base_path('assets/backend/card-studio/icons.json')), true),
            'sizes' => CardPresets::SIZES,
            'sample' => CardData::sample(),
        ]);
    }

    /** Save the design (a new version). "base" is the version the editor started from: refuse if someone saved since. */
    public function update(Request $request, CardTemplate $template){
        $data = $request->validate([
            'design' => 'required|array',
            'name' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:200',
            'base' => 'required|integer',
            'force' => 'nullable|boolean',
        ]);
        if ((int) $data['base'] !== (int) $template->version && empty($data['force'])) {
            return response()->json(['success' => false, 'conflict' => true, 'version' => $template->version,
                'message' => 'Someone saved this template (version ' . $template->version . ') after you opened it.'], 409);
        }
        $template->saveDesign($data['design'], $data['name'] ?? null, auth()->id(), $data['note'] ?? null);
        return response()->json(['success' => true, 'message' => 'Saved as version ' . $template->version . '.', 'version' => $template->version,
            'design' => $template->design, 'versions' => $this->versionList($template)]);
    }

    /** Where the template is used: default for everyone, for an event, or for an event + category. */
    public function settings(Request $request, CardTemplate $template){
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'event_id' => 'nullable|integer|exists:events,id',
            'category' => 'nullable|string|max:100',
            'is_default' => 'required|boolean',
        ]);
        if (empty($data['event_id'])) $data['category'] = null;
        if (!empty($data['event_id']) && !empty($data['category'])) {
            $taken = CardTemplate::where('event_id', $data['event_id'])->where('category', $data['category'])->where('id', '!=', $template->id)->value('name');
            if ($taken) return response()->json(['success' => false, 'message' => '"' . $taken . '" is already used for this event and category.'], 422);
        }
        DB::transaction(function () use ($template, $data) {
            if ($data['is_default']) CardTemplate::where('id', '!=', $template->id)->update(['is_default' => false]);
            elseif ($template->is_default) {
                // there must always be a default: keep it until another one is chosen
                $data['is_default'] = true;
            }
            $template->fill(['name' => trim($data['name']), 'event_id' => $data['event_id'] ?: null, 'category' => $data['category'] ? trim($data['category']) : null,
                'is_default' => $data['is_default']])->save();
        });
        return response()->json(['success' => true, 'message' => $template->is_default && !$request->boolean('is_default')
            ? 'Saved. This is still the default template — make another template the default first.' : 'Saved.']);
    }

    public function duplicate(CardTemplate $template){
        $copy = CardTemplate::createFrom(Str::limit('Copy of ' . $template->name, 120, ''), $template->design, auth()->id());
        return response()->json(['success' => true, 'message' => 'Copied.', 'edit' => route('card-studio.edit', $copy)]);
    }

    public function destroy(CardTemplate $template){
        if ($template->is_default) return response()->json(['success' => false, 'message' => 'This is the default template. Make another template the default first.'], 422);
        $template->delete();
        return response()->json(['success' => true, 'message' => 'Template deleted.']);
    }

    public function version(CardTemplate $template, int $version){
        $v = DB::table('card_template_versions')->where('template_id', $template->id)->where('version', $version)->first();
        abort_unless($v, 404);
        return response()->json(['version' => $v->version, 'name' => $v->name, 'design' => CardTemplate::clean(json_decode($v->design, true) ?: [])]);
    }

    /** Images for designs (logos, backgrounds, artwork). */
    public function upload(Request $request){
        $request->validate(['image' => 'required|file|max:5120|mimes:png,jpg,jpeg,webp'], ['image.mimes' => 'Please upload a PNG, JPG or WebP image.', 'image.max' => 'The image must be smaller than 5 MB.']);
        $file = $request->file('image');
        $ext = strtolower($file->getClientOriginalExtension());
        $ext = in_array($ext, ['png', 'webp'], true) ? $ext : 'jpg';
        $name = Str::lower(Str::random(16)) . '.' . $ext;
        $file->move(base_path('upload/card-studio'), $name);
        $path = 'upload/card-studio/' . $name;
        $size = @getimagesize(base_path($path));
        if ($size && max($size[0], $size[1]) > 3000) \App\Support\Thumb::shrink(base_path($path), base_path($path), 3000);
        return response()->json(['success' => true, 'path' => $path, 'url' => asset($path)]);
    }

    /** Participant search for previews. */
    public function participants(Request $request){
        $s = trim((string) $request->input('q'));
        if (mb_strlen($s) < 2) return response()->json([]);
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
        return response()->json(Participant::where('status', 'active')
            ->where(fn ($w) => $w->where('code', 'like', $like)->orWhere(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'like', $like)->orWhere('email', 'like', $like)->orWhere('organization', 'like', $like))
            ->orderBy('first_name')->limit(15)->get()->map(fn ($p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'organization' => $p->organization, 'category' => $p->category]));
    }

    /** Card data for one participant (placeholders, photo, QR links). */
    public function data(Request $request, Participant $participant){
        $event = $request->filled('event') ? Event::find((int) $request->input('event')) : null;
        return response()->json(CardData::for($participant, $event));
    }

    /** Preview many participants with the template each one would get. */
    public function preview(Request $request){
        $event = $request->filled('event') ? Event::find((int) $request->input('event')) : \App\Support\PinnedEvent::event();
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->input('ids')))), 0, 60);
        $q = Participant::where('status', 'active');
        $people = $ids ? $q->whereIn('id', $ids)->get() : $q->orderBy('sequence')->limit(12)->get();
        $only = $request->filled('template') ? CardTemplate::find((int) $request->input('template')) : null;
        $templates = [];
        $cards = $people->map(function ($p) use ($event, $only, &$templates) {
            $t = $only ?: CardTemplate::forParticipant($p, $event);
            if ($t) $templates[$t->id] = ['id' => $t->id, 'name' => $t->name, 'design' => $t->design];
            return ['template' => $t?->id, 'data' => CardData::for($p, $event), 'name' => $p->name, 'code' => $p->code];
        })->values();
        return view('backend.pages.card-studio.preview', [
            'event' => $event, 'events' => $this->events(), 'cards' => $cards, 'templates' => array_values($templates), 'only' => $only,
            'all' => CardTemplate::orderBy('name')->get(['id', 'name']), 'ids' => $ids,
            'icons' => json_decode((string) file_get_contents(base_path('assets/backend/card-studio/icons.json')), true),
        ]);
    }

    private function versionList(CardTemplate $t){
        return DB::table('card_template_versions as v')->leftJoin('users as u', 'u.id', '=', 'v.user_id')->where('v.template_id', $t->id)
            ->orderByDesc('v.version')->limit(60)->get(['v.version', 'v.name', 'v.note', 'v.created_at', 'u.name as user'])
            ->map(fn ($v) => ['version' => $v->version, 'note' => $v->note, 'user' => $v->user, 'at' => $v->created_at ? \Carbon\Carbon::parse($v->created_at)->format('j M Y, H:i') : '']);
    }

    private function events(){
        return Event::orderByDesc('is_current')->orderByDesc('event_start')->orderByDesc('id')->get(['id', 'event_short', 'is_current']);
    }

    private function categories(){
        return Participant::whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->limit(100)->pluck('category');
    }
}
