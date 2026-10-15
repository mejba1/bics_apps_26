<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageSection;
use App\Support\SectionTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin > Pages: edit the content of every website page, section by section (AJAX).
 */
class PageBuilderController extends Controller
{
    /* ---------- Pages ---------- */

    public function index(){
        $pages = Page::withCount('sections')->orderByDesc('is_system')->orderBy('id')->get()
            ->sortBy(fn ($p) => $p->is_global ? 1 : 0)->values();
        return view('backend.pages.cms.index', compact('pages'));
    }

    public function edit(Page $page){
        $sections = $page->sections()->get()->map(fn ($s) => $this->sectionJson($s));
        $types = $page->is_global ? [] : SectionTypes::forPages();
        return view('backend.pages.cms.edit', [
            'page' => $page,
            'sections' => $sections,
            'types' => $types,
            'allTypes' => SectionTypes::all(),
        ]);
    }

    public function store(Request $request){
        $data = $request->validate([
            'title' => 'required|max:150',
            'slug' => $this->slugRules(),
        ]);
        $page = Page::create([
            'title' => $data['title'],
            'slug' => ($data['slug'] ?? null) ?: $this->uniqueSlug($data['title']),
            'is_active' => true,
        ]);
        return response()->json(['success' => true, 'redirect' => route('cms.edit', $page)]);
    }

    public function update(Request $request, Page $page){
        $data = $request->validate([
            'title' => 'required|max:150',
            'slug' => $page->is_system ? 'nullable' : $this->slugRules($page),
            'meta_title' => 'nullable|max:255',
            'meta_description' => 'nullable|max:500',
            'is_active' => 'nullable|boolean',
        ]);
        // Built-in pages keep their address; the home page and site-wide page are always on
        if ($page->is_system) {
            unset($data['slug']);
        } elseif (empty($data['slug'])) {
            $data['slug'] = $page->slug;
        }
        $data['is_active'] = in_array($page->slug, ['home', Page::GLOBAL_SLUG], true) ? true : $request->boolean('is_active');
        $page->update($data);
        return response()->json(['success' => true, 'message' => 'Page settings saved.', 'url' => $page->url]);
    }

    public function destroy(Page $page){
        abort_if($page->is_system, 403, 'Built-in pages cannot be deleted. You can hide them instead.');
        $page->delete();
        return response()->json(['success' => true, 'redirect' => route('cms.index')]);
    }

    /* ---------- Sections ---------- */

    public function addSection(Request $request, Page $page){
        abort_if($page->is_global, 403);
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(SectionTypes::forPages()))]]);
        $type = SectionTypes::get($data['type']);
        $section = $page->sections()->create([
            'type' => $data['type'],
            'label' => null,
            'content' => SectionTypes::clean($data['type'], $type['defaults'] ?? []),
            'sort' => (int) $page->sections()->max('sort') + 1,
            'is_active' => true,
        ]);
        return response()->json(['success' => true, 'section' => $this->sectionJson($section)]);
    }

    public function updateSection(Request $request, PageSection $section){
        $data = $request->validate([
            'label' => 'nullable|max:150',
            'content' => 'nullable|array',
        ]);
        $section->update([
            'label' => $data['label'] ?? null,
            'content' => SectionTypes::clean($section->type, $data['content'] ?? []),
        ]);
        return response()->json(['success' => true, 'message' => 'Section saved.', 'section' => $this->sectionJson($section)]);
    }

    public function toggleSection(PageSection $section){
        abort_if($section->page->is_global, 403, 'Site-wide sections are always shown.');
        $section->update(['is_active' => !$section->is_active]);
        return response()->json(['success' => true, 'section' => $this->sectionJson($section)]);
    }

    public function duplicateSection(PageSection $section){
        abort_if($section->page->is_global, 403);
        $copy = DB::transaction(function () use ($section) {
            // Make room right after the original
            PageSection::where('page_id', $section->page_id)->where('sort', '>', $section->sort)->increment('sort');
            return PageSection::create([
                'page_id' => $section->page_id,
                'type' => $section->type,
                'label' => ($section->label ?: $section->definition['label']) . ' (copy)',
                'content' => $section->content,
                'sort' => $section->sort + 1,
                'is_active' => false,
            ]);
        });
        return response()->json(['success' => true, 'section' => $this->sectionJson($copy), 'after' => $section->id]);
    }

    public function destroySection(PageSection $section){
        abort_if($section->page->is_global, 403);
        $section->delete();
        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, Page $page){
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        DB::transaction(function () use ($ids, $page) {
            foreach (array_values($ids) as $i => $id) {
                PageSection::where('id', $id)->where('page_id', $page->id)->update(['sort' => $i + 1]);
            }
        });
        return response()->json(['success' => true]);
    }

    /* ---------- Image upload (used by image fields and the text editor) ---------- */

    public function upload(Request $request){
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:5120']);
        $file = $request->file('file');
        $dir = 'upload/cms/' . date('Y/m');
        File::ensureDirectoryExists(base_path($dir));
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
        $name = Str::limit($name, 60, '') . '-' . Str::random(6) . '.' . strtolower($file->getClientOriginalExtension());
        $file->move(base_path($dir), $name);
        $path = $dir . '/' . $name;
        // "location" is the key TinyMCE expects
        return response()->json(['success' => true, 'path' => $path, 'url' => asset($path), 'location' => asset($path)]);
    }

    /* ---------- Helpers ---------- */

    private function sectionJson(PageSection $s): array
    {
        $def = $s->definition;
        return [
            'id' => $s->id,
            'type' => $s->type,
            'type_label' => $def['label'],
            'icon' => $def['icon'] ?? 'bi-square',
            'label' => $s->label,
            'display_label' => $s->display_label,
            'content' => $s->content ?? [],
            'is_active' => (bool) $s->is_active,
            'urls' => [
                'update' => route('cms.sections.update', $s),
                'toggle' => route('cms.sections.toggle', $s),
                'duplicate' => route('cms.sections.duplicate', $s),
                'destroy' => route('cms.sections.destroy', $s),
            ],
        ];
    }

    private function slugRules(?Page $ignore = null): array
    {
        return ['nullable', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn($this->reservedSlugs()),
            Rule::unique('pages', 'slug')->ignore($ignore?->id)];
    }

    /** Addresses already used by the application (registration, login, ...). */
    private function reservedSlugs(): array
    {
        $reserved = ['assets', 'upload', 'vendor', 'storage', 'resources', 'public', 'cms', 'admin', 'api', 'index', Page::GLOBAL_SLUG];
        foreach (Route::getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0];
            if ($first !== '' && !str_starts_with($first, '{')) {
                $reserved[] = strtolower($first);
            }
        }
        return array_values(array_unique($reserved));
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'page';
        $slug = $base;
        $i = 2;
        while (in_array($slug, $this->reservedSlugs(), true) || Page::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
