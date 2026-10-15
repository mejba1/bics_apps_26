<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Website menu admin. Runs inside a transaction, so nothing is saved to the database.
 */
class MenuManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role): User
    {
        $user = new User();
        $user->forceFill(['name' => 'Test '.$role, 'email' => uniqid($role).'@test.local', 'password' => bcrypt('secret-pass'), 'role' => $role])->save();
        return $user;
    }

    public function test_only_admins_can_manage_the_menu(): void
    {
        $this->get('/menu')->assertRedirect('/login');
        $this->actingAs($this->user('user'))->get('/menu')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/menu')->assertOk()->assertSee('Website Menu')->assertSee('BICS Overview');
    }

    public function test_admin_can_add_edit_hide_reorder_and_delete(): void
    {
        $this->actingAs($this->user('admin'));
        $gallery = MenuItem::where('title', 'Gallery')->whereNull('parent_id')->firstOrFail();

        // Add a top-level item and a sub-item
        $this->post('/menu', ['title' => 'Speakers', 'url' => 'speakers', 'is_active' => 1])->assertRedirect('/menu');
        $speakers = MenuItem::where('title', 'Speakers')->firstOrFail();
        $this->post('/menu', ['title' => 'Keynotes', 'url' => 'https://example.com', 'parent_id' => $speakers->id, 'is_active' => 1, 'new_tab' => 1]);
        $this->get('/')->assertSee('Speakers')->assertSee('Keynotes')->assertSee('target="_blank"', false);

        // Edit
        $this->put("/menu/{$speakers->id}", ['title' => 'Our Speakers', 'url' => 'speakers', 'is_active' => 1])->assertRedirect('/menu');
        $this->get('/')->assertSee('Our Speakers');

        // Hide / show
        $this->post("/menu/{$gallery->id}/toggle");
        $this->get('/')->assertDontSee('>Gallery</a>', false);
        $this->post("/menu/{$gallery->id}/toggle");
        $this->get('/')->assertSee('>Gallery</a>', false);

        // Move Gallery up: it should now come before Contact
        $this->post("/menu/{$gallery->id}/move/up");
        $html = $this->get('/')->getContent();
        $this->assertLessThan(strpos($html, 'href="'.url('contact-us').'"'), strpos($html, 'href="'.url('gallery').'"'));

        // Delete parent removes its sub-menu too
        $this->delete("/menu/{$speakers->id}")->assertRedirect('/menu');
        $this->assertDatabaseMissing('menu_items', ['title' => 'Keynotes']);
        $this->get('/')->assertDontSee('Our Speakers');
    }

    public function test_validation_and_two_level_limit(): void
    {
        $this->actingAs($this->user('admin'));
        $this->post('/menu', ['title' => '', 'url' => ''])->assertSessionHasErrors(['title', 'url']);

        $child = MenuItem::whereNotNull('parent_id')->firstOrFail();
        $this->post('/menu', ['title' => 'Too deep', 'url' => '#', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');

        $parentWithChildren = MenuItem::where('title', 'BICS Overview')->firstOrFail();
        $home = MenuItem::where('title', 'Home')->firstOrFail();
        $this->put("/menu/{$parentWithChildren->id}", ['title' => 'BICS Overview', 'url' => 'bics-overview', 'parent_id' => $home->id])
            ->assertSessionHasErrors('parent_id');
    }
}
