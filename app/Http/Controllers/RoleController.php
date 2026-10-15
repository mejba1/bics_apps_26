<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Roles & Permissions (permission "roles.manage"). AJAX.
 * The Super Admin role always has every permission and cannot be changed or deleted.
 * People who are not Super Admin can only give out permissions they have themselves.
 */
class RoleController extends Controller
{
    public function index(Request $request){
        $roles = Role::withCount('users')->orderByDesc('is_super')->orderBy('name')->get();
        return view('backend.pages.roles', [
            'roles' => $roles->map(fn ($r) => $this->json($r))->values(),
            'groups' => Permissions::groups(),
            'mine' => $request->user()->permissions(),
            'isSuper' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function store(Request $request){
        $data = $this->validated($request);
        $role = Role::create(['name' => $data['name'], 'description' => $data['description'], 'is_super' => false]);
        $role->syncPermissions($data['permissions']);
        return response()->json(['success' => true, 'message' => 'Role created.', 'role' => $this->json($role->loadCount('users'))]);
    }

    public function update(Request $request, Role $role){
        if ($role->is_super) $this->fail('name', 'The Super Admin role always has full access and cannot be changed.');
        $data = $this->validated($request, $role);
        $role->update(['name' => $data['name'], 'description' => $data['description']]);
        $role->syncPermissions($data['permissions']);
        return response()->json(['success' => true, 'message' => 'Role saved.', 'role' => $this->json($role->loadCount('users'))]);
    }

    public function destroy(Role $role){
        if ($role->is_super) $this->fail('role', 'The Super Admin role cannot be deleted.');
        if ($role->users()->exists()) $this->fail('role', 'Move the ' . $role->users()->count() . ' user(s) with this role to another role first.');
        $role->delete();
        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(array_keys(Permissions::all()))],
        ], ['name.unique' => 'A role with this name already exists.']);

        $permissions = $data['permissions'] ?? [];
        $me = $request->user();
        if (!$me->isSuperAdmin()) {
            $extra = array_diff($permissions, $me->permissions());
            if ($extra) $this->fail('permissions', 'You can only give permissions you have yourself: ' . implode(', ', array_map(fn ($k) => Permissions::all()[$k], $extra)) . '.');
        }
        return ['name' => trim($data['name']), 'description' => trim((string) ($data['description'] ?? '')) ?: null, 'permissions' => $permissions];
    }

    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function json(Role $r): array
    {
        return ['id' => $r->id, 'name' => $r->name, 'description' => $r->description, 'is_super' => (bool) $r->is_super,
            'users' => (int) ($r->users_count ?? 0), 'permissions' => $r->permissionKeys()];
    }
}
