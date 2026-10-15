<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Users (permission "users.manage") and My Account (every logged-in user). AJAX.
 *
 * Safety rules:
 * - nobody can delete, deactivate or change the role of their own account (no self lock-out);
 * - the last active Super Admin cannot be removed, deactivated or demoted;
 * - only a Super Admin can give the Super Admin role or edit a Super Admin's account.
 */
class UserController extends Controller
{
    public function index(Request $request){
        $users = User::with('roleModel')->orderBy('name')->get();
        return view('backend.pages.users', [
            'users' => $users->map(fn ($u) => $this->json($u, $request->user()))->values(),
            'roles' => Role::orderByDesc('is_super')->orderBy('name')->get(['id', 'name', 'is_super']),
            'canGrantSuper' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function store(Request $request){
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:100',
            'role_id' => 'required|integer|exists:roles,id',
            'status' => 'required|in:active,inactive',
        ], ['email.unique' => 'A user with this email already exists.']);
        $role = Role::findOrFail($data['role_id']);
        $this->guardSuperRole($request->user(), $role);

        $user = new User();
        $user->forceFill([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role_id' => $role->id,
            'role' => $role->is_super ? 'admin' : 'user', // old column, kept in step
            'status' => $data['status'],
        ])->save();

        return response()->json(['success' => true, 'message' => 'User created.', 'user' => $this->json($user->fresh('roleModel'), $request->user())]);
    }

    public function update(Request $request, User $user){
        $me = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8|max:100',
            'role_id' => 'required|integer|exists:roles,id',
            'status' => 'required|in:active,inactive',
        ], ['email.unique' => 'A user with this email already exists.']);
        $role = Role::findOrFail($data['role_id']);

        if ($user->isSuperAdmin() && !$me->isSuperAdmin()) $this->fail('role_id', 'Only a Super Admin can edit a Super Admin account.');
        $this->guardSuperRole($me, $role);
        if ($user->id === $me->id) {
            if ((int) $data['role_id'] !== (int) $user->role_id) $this->fail('role_id', 'You cannot change your own role.');
            if ($data['status'] !== 'active') $this->fail('status', 'You cannot deactivate your own account.');
        }
        $losesSuper = $user->isSuperAdmin() && (!$role->is_super || $data['status'] !== 'active');
        if ($losesSuper && $this->activeSuperAdmins($user->id) === 0) {
            $this->fail('role_id', 'This is the last active Super Admin. Make someone else Super Admin first.');
        }

        $changes = [
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'role_id' => $role->id,
            'role' => $role->is_super ? 'admin' : 'user',
            'status' => $data['status'],
        ];
        if (!empty($data['password'])) $changes['password'] = Hash::make($data['password']);
        $user->forceFill($changes)->save();

        return response()->json(['success' => true, 'message' => 'User updated.', 'user' => $this->json($user->fresh('roleModel'), $me)]);
    }

    public function destroy(Request $request, User $user){
        $me = $request->user();
        if ($user->id === $me->id) $this->fail('user', 'You cannot delete your own account.');
        if ($user->isSuperAdmin() && !$me->isSuperAdmin()) $this->fail('user', 'Only a Super Admin can delete a Super Admin.');
        if ($user->isSuperAdmin() && $this->activeSuperAdmins($user->id) === 0) $this->fail('user', 'This is the last active Super Admin and cannot be deleted.');
        $user->delete();
        return response()->json(['success' => true, 'message' => 'User deleted.']);
    }

    /* ---------------- My account ---------------- */

    public function account(Request $request){
        $user = $request->user();
        $groups = [];
        foreach (\App\Support\Permissions::groups() as $group => $items) {
            $mine = array_values(array_filter(array_keys($items), fn ($k) => $user->hasPermission($k)));
            if ($mine) $groups[$group] = array_map(fn ($k) => $items[$k][0], $mine);
        }
        return view('backend.pages.account', ['user' => $user, 'groups' => $groups]);
    }

    public function updateAccount(Request $request){
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => 'nullable|required_with:password|string',
            'password' => 'nullable|string|min:8|max:100|confirmed',
        ], ['current_password.required_with' => 'Enter your current password to set a new one.']);
        if (!empty($data['password']) && !Hash::check($data['current_password'], $user->password)) {
            $this->fail('current_password', 'Your current password is not correct.');
        }
        $changes = ['name' => trim($data['name']), 'email' => strtolower(trim($data['email']))];
        if (!empty($data['password'])) $changes['password'] = Hash::make($data['password']);
        $user->forceFill($changes)->save();
        return response()->json(['success' => true, 'message' => 'Your account has been updated.']);
    }

    /* ---------------- Helpers ---------------- */

    private function guardSuperRole(User $me, Role $role): void
    {
        if ($role->is_super && !$me->isSuperAdmin()) $this->fail('role_id', 'Only a Super Admin can give the Super Admin role.');
    }

    private function activeSuperAdmins(int $exceptId): int
    {
        return User::where('id', '!=', $exceptId)->where('status', 'active')
            ->whereHas('roleModel', fn ($q) => $q->where('is_super', true))->count();
    }

    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function json(User $u, User $me): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'status' => $u->status ?? 'active',
            'role_id' => $u->role_id, 'role_name' => $u->role_name, 'is_super' => $u->isSuperAdmin(),
            'is_me' => $u->id === $me->id, 'created' => optional($u->created_at)->format('j M Y'),
        ];
    }
}
