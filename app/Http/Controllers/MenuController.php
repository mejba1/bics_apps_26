<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    //Menu list with add / edit forms
    public function index(){
        $menus = MenuItem::whereNull('parent_id')->with('children')->orderBy('sort')->orderBy('id')->get();
        return view('backend.pages.menu', compact('menus'));
    }

    public function store(Request $request){
        $data = $this->validated($request);
        // New items go to the end of their level
        $data['sort'] = (int) MenuItem::where('parent_id', $data['parent_id'])->max('sort') + 1;
        MenuItem::create($data);
        return redirect()->route('menu.index')->with('success', 'Menu item added.');
    }

    public function update(Request $request, MenuItem $menu){
        $data = $this->validated($request, $menu);
        if ((int) $menu->parent_id !== (int) $data['parent_id']) {
            $data['sort'] = (int) MenuItem::where('parent_id', $data['parent_id'])->max('sort') + 1;
        }
        $menu->update($data);
        return redirect()->route('menu.index')->with('success', 'Menu item updated.');
    }

    public function destroy(MenuItem $menu){
        // Sub-menu items are removed with their parent (cascade)
        $menu->delete();
        return redirect()->route('menu.index')->with('success', 'Menu item deleted.');
    }

    public function toggle(MenuItem $menu){
        $menu->update(['is_active' => !$menu->is_active]);
        return redirect()->route('menu.index')->with('success', $menu->title.($menu->is_active ? ' is now visible.' : ' is now hidden.'));
    }

    //Move an item one place up or down within its own level
    public function move(MenuItem $menu, string $direction){
        $siblings = MenuItem::where('parent_id', $menu->parent_id)->orderBy('sort')->orderBy('id')->get()->values();
        $index = $siblings->search(fn ($item) => $item->id === $menu->id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swap >= 0 && $swap < $siblings->count()) {
            $ordered = $siblings->all();
            [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
            foreach ($ordered as $position => $item) {
                $item->update(['sort' => $position + 1]);
            }
        }
        return redirect()->route('menu.index');
    }

    private function validated(Request $request, ?MenuItem $menu = null): array
    {
        $data = $request->validate([
            'title' => 'required|max:100',
            'url' => 'required|max:255',
            'parent_id' => 'nullable|integer|exists:menu_items,id',
            'new_tab' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['new_tab'] = $request->boolean('new_tab');
        $data['is_active'] = $request->boolean('is_active');

        // Only two levels: a parent must be a top-level item, and an item with sub-menus stays top-level
        if ($data['parent_id']) {
            $parent = MenuItem::find($data['parent_id']);
            if ($parent->parent_id || ($menu && ($parent->id === $menu->id || $menu->children()->exists()))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'Choose a top-level item without making a loop (menus are two levels deep).']);
            }
        }
        return $data;
    }
}
