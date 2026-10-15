<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Role-based access. Runs inside a transaction, so nothing is saved.
 */
class RolesPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    private function role(string $name, array $permissions, bool $super = false): Role
    {
        $role = Role::create(['name' => $name . uniqid(), 'description' => null, 'is_super' => $super]);
        $role->syncPermissions($permissions);
        return $role;
    }

    private function userWith(Role $role, string $status = 'active'): User
    {
        $u = new User();
        $u->forceFill(['name' => 'Test ' . $role->name, 'email' => uniqid('r') . '@test.local', 'password' => bcrypt('secret-pass'),
            'role' => $role->is_super ? 'admin' : 'user', 'role_id' => $role->id, 'status' => $status])->save();
        return $u;
    }

    private function superAdmin(): User
    {
        return $this->userWith(Role::where('is_super', true)->firstOrFail());
    }

    public function test_routes_follow_the_role_permissions(): void
    {
        $editor = $this->userWith($this->role('Editor', ['website.pages', 'website.gallery']));
        $this->actingAs($editor);
        $this->get('/cms/pages')->assertOk();
        $this->get('/gallery-manager')->assertOk();
        foreach (['/menu', '/form-options', '/event-settings', '/users', '/roles', '/verify-user', '/add-prize', '/raffle', '/createPdf'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->deleteJson('/delete_register/999999')->assertForbidden();

        // Without dashboard permission, /home sends them to the first page they may use
        $this->get('/home')->assertRedirect('/cms/pages');
    }

    public function test_menu_and_buttons_only_show_what_is_allowed(): void
    {
        $desk = $this->userWith($this->role('Desk', ['registrations.view', 'attendees.verify']));
        $html = $this->actingAs($desk)->get('/home')->assertOk()->getContent();
        $this->assertStringContainsString('Verify Attendee', $html);
        $this->assertStringNotContainsString('Gift Distribution', $html);
        $this->assertStringNotContainsString('Website Pages', $html);
        $this->assertStringNotContainsString('Roles &amp; Permissions', $html);
        $this->assertStringNotContainsString('btn-sm deleteRecord', $html); // attendee table hidden (no registrations.list)
        $this->assertStringNotContainsString('download-profile-Pdf', $html);
        $this->assertStringNotContainsString(route('createPdf'), $html);   // no export

        $lister = $this->userWith($this->role('Lister', ['registrations.view', 'registrations.list']));
        $html = $this->actingAs($lister)->get('/home')->getContent();
        $this->assertStringNotContainsString('btn btn-danger btn-sm deleteRecord', $html); // list shown, but no delete button
    }

    public function test_no_permissions_shows_a_friendly_page_and_inactive_users_have_nothing(): void
    {
        $empty = $this->userWith($this->role('Empty', []));
        $this->actingAs($empty)->get('/home')->assertOk()->assertSee('does not have access to any admin pages yet');
        $this->get('/account')->assertOk()->assertSee('No permissions yet');

        $inactive = $this->userWith($this->role('Full', ['website.pages']), 'inactive');
        $this->actingAs($inactive)->get('/cms/pages')->assertForbidden();
    }

    public function test_role_management(): void
    {
        $this->actingAs($this->superAdmin());
        $role = $this->postJson('/roles', ['name' => 'Volunteers', 'description' => 'Check-in helpers', 'permissions' => ['attendees.verify', 'nope.fake']])
            ->assertStatus(422)->json(); // unknown permission rejected
        $role = $this->postJson('/roles', ['name' => 'Volunteers', 'permissions' => ['attendees.verify']])->assertOk()->json('role');
        $this->assertSame(['attendees.verify'], $role['permissions']);
        $this->putJson("/roles/{$role['id']}", ['name' => 'Volunteers', 'permissions' => ['attendees.verify', 'attendees.gifts']])->assertOk();
        $this->assertEqualsCanonicalizing(['attendees.verify', 'attendees.gifts'], Role::find($role['id'])->permissionKeys());

        // Super Admin role is locked
        $super = Role::where('is_super', true)->first();
        $this->putJson("/roles/{$super->id}", ['name' => 'X', 'permissions' => []])->assertStatus(422);
        $this->deleteJson("/roles/{$super->id}")->assertStatus(422);

        // A role in use cannot be deleted
        $this->userWith(Role::find($role['id']));
        $this->deleteJson("/roles/{$role['id']}")->assertStatus(422);
    }

    public function test_cannot_give_away_permissions_you_do_not_have(): void
    {
        $manager = $this->userWith($this->role('RoleManager', ['roles.manage', 'website.pages']));
        $this->actingAs($manager)->postJson('/roles', ['name' => 'Sneaky', 'permissions' => ['website.pages', 'users.manage']])
            ->assertStatus(422)->assertJsonValidationErrors('permissions');
        $this->postJson('/roles', ['name' => 'Fine', 'permissions' => ['website.pages']])->assertOk();
    }

    public function test_user_management_and_safety_rules(): void
    {
        $me = $this->superAdmin();
        $this->actingAs($me);
        $desk = Role::where('name', 'Registration Desk')->firstOrFail();
        $super = Role::where('is_super', true)->first();

        $u = $this->postJson('/users', ['name' => 'New Person', 'email' => 'new.person@test.local', 'password' => 'Str0ng-pass', 'role_id' => $desk->id, 'status' => 'active'])
            ->assertOk()->json('user');
        $this->assertSame('Registration Desk', $u['role_name']);
        $this->postJson('/users', ['name' => 'Dup', 'email' => 'new.person@test.local', 'password' => 'Str0ng-pass', 'role_id' => $desk->id, 'status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        // The new person can log in and lands on their first allowed page
        $this->post('/logout');
        auth()->logout();
        $this->post('/custom-login', ['email' => 'new.person@test.local', 'password' => 'Str0ng-pass'])->assertRedirect('/home');
        auth()->logout();

        // No self lock-out
        $this->actingAs($me);
        $this->putJson("/users/{$me->id}", ['name' => $me->name, 'email' => $me->email, 'role_id' => $desk->id, 'status' => 'active'])->assertStatus(422);
        $this->putJson("/users/{$me->id}", ['name' => $me->name, 'email' => $me->email, 'role_id' => $super->id, 'status' => 'inactive'])->assertStatus(422);
        $this->deleteJson("/users/{$me->id}")->assertStatus(422);

        // Only a Super Admin can make Super Admins
        $usersManager = $this->userWith($this->role('UM', ['users.manage']));
        $this->actingAs($usersManager)->postJson('/users', ['name' => 'X', 'email' => 'x.super@test.local', 'password' => 'Str0ng-pass', 'role_id' => $super->id, 'status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('role_id');
        $this->putJson("/users/{$me->id}", ['name' => 'Hacked', 'email' => $me->email, 'role_id' => $super->id, 'status' => 'active'])->assertStatus(422);

        // Deactivated users cannot log in
        auth()->logout();
        $this->actingAs($me)->putJson("/users/{$u['id']}", ['name' => 'New Person', 'email' => 'new.person@test.local', 'role_id' => $desk->id, 'status' => 'inactive'])->assertOk();
        auth()->logout();
        $this->post('/custom-login', ['email' => 'new.person@test.local', 'password' => 'Str0ng-pass'])->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_last_super_admin_is_protected(): void
    {
        $super = Role::where('is_super', true)->first();
        $desk = Role::where('name', 'Registration Desk')->firstOrFail();
        // Make the test admin the only active Super Admin
        User::where('role_id', $super->id)->update(['status' => 'inactive']);
        $me = $this->superAdmin();
        $other = $this->userWith($this->role('Users', ['users.manage']));
        $this->actingAs($me);

        // Another super admin would be fine; with only one, demoting / deleting is refused
        $second = $this->userWith($super);
        $this->deleteJson("/users/{$second->id}")->assertOk(); // others remain (me)
        $this->putJson("/users/{$me->id}", ['name' => $me->name, 'email' => $me->email, 'role_id' => $super->id, 'status' => 'active'])->assertOk();
        $this->assertSame(1, User::where('role_id', $super->id)->where('status', 'active')->count());
        $this->actingAs($other)->deleteJson("/users/{$me->id}")->assertStatus(422);
        $this->assertTrue($desk->exists);
    }

    public function test_my_account(): void
    {
        $u = $this->userWith($this->role('Acc', ['website.menu']));
        $this->actingAs($u)->get('/account')->assertOk()->assertSee('Website menu');
        $this->putJson('/account', ['name' => 'Renamed', 'email' => $u->email, 'current_password' => 'wrong', 'password' => 'N3w-password', 'password_confirmation' => 'N3w-password'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->putJson('/account', ['name' => 'Renamed', 'email' => $u->email, 'current_password' => 'secret-pass', 'password' => 'N3w-password', 'password_confirmation' => 'N3w-password'])
            ->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('N3w-password', $u->fresh()->password));
    }
}
