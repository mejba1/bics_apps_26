<?php

namespace App\Http\Controllers;

use App\Models\FormOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin > Form Options: the values of every drop-down on the registration form (AJAX).
 * Registrations store the chosen text, so renaming or deleting a value never changes past registrations.
 */
class FormOptionController extends Controller
{
    public function index(){
        $options = FormOption::orderBy('sort')->orderBy('id')->get(['id', 'list', 'parent_id', 'label', 'extra', 'sort', 'is_active']);
        return view('backend.pages.form-options', [
            'lists' => FormOption::LISTS,
            'options' => $options->map(fn ($o) => $this->json($o))->values(),
        ]);
    }

    public function store(Request $request){
        $data = $this->validated($request);
        $data['sort'] = (int) FormOption::where('list', $data['list'])->where('parent_id', $data['parent_id'])->max('sort') + 1;
        $data['is_active'] = true;
        $option = FormOption::create($data);
        return response()->json(['success' => true, 'option' => $this->json($option)]);
    }

    public function update(Request $request, FormOption $option){
        $data = $this->validated($request, $option);
        $option->update(['label' => $data['label'], 'extra' => $data['extra']]);
        return response()->json(['success' => true, 'option' => $this->json($option)]);
    }

    public function toggle(FormOption $option){
        $option->update(['is_active' => !$option->is_active]);
        return response()->json(['success' => true, 'option' => $this->json($option)]);
    }

    public function destroy(FormOption $option){
        $children = $option->children()->count();
        $option->delete(); // specialities of a profession are removed with it
        return response()->json(['success' => true, 'removed_children' => $children]);
    }

    public function reorder(Request $request){
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) FormOption::where('id', $id)->update(['sort' => $i + 1]);
        });
        return response()->json(['success' => true]);
    }

    private function validated(Request $request, ?FormOption $option = null): array
    {
        $list = $option ? $option->list : $request->input('list');
        $parentId = $option ? $option->parent_id : $request->input('parent_id');

        $data = $request->validate([
            'list' => $option ? 'nullable' : ['required', Rule::in(['title', 'profession', 'speciality', 'id_type', 'country'])],
            'parent_id' => $option ? 'nullable' : [Rule::requiredIf($list === 'speciality'), 'nullable', 'integer',
                Rule::exists('form_options', 'id')->where('list', 'profession')],
            'label' => ['required', 'string', 'max:150',
                Rule::unique('form_options', 'label')->where(fn ($q) => $q->where('list', $list)->where(fn ($w) => $parentId ? $w->where('parent_id', $parentId) : $w->whereNull('parent_id')))
                    ->ignore($option?->id)],
            'extra' => $list === 'country' ? ['nullable', 'regex:/^\+?[0-9][0-9\- ]{0,9}$/'] : ['nullable', 'max:50'],
        ], [
            'label.unique' => 'This value is already in the list.',
            'extra.regex' => 'Dial code should look like +34 or +1-268.',
        ], ['label' => 'name', 'extra' => 'dial code']);

        $extra = trim((string) ($data['extra'] ?? ''));
        if ($list === 'country' && $extra !== '') $extra = '+' . ltrim($extra, '+');

        return [
            'list' => $list,
            'parent_id' => $list === 'speciality' ? (int) $parentId : null,
            'label' => trim($data['label']),
            'extra' => $list === 'country' ? ($extra ?: null) : null,
        ];
    }

    private function json(FormOption $o): array
    {
        return ['id' => $o->id, 'list' => $o->list, 'parent_id' => $o->parent_id, 'label' => $o->label, 'extra' => $o->extra, 'is_active' => (bool) $o->is_active];
    }
}
