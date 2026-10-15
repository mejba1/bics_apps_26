<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Admin > Website Pages. Runs inside a transaction, so nothing is saved to the database.
 */
class PageBuilderTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role): User
    {
        $user = new User();
        $user->forceFill(['name' => 'Test '.$role, 'email' => uniqid($role).'@test.local', 'password' => bcrypt('secret-pass'), 'role' => $role])->save();
        return $user;
    }

    private function admin(): self
    {
        return $this->actingAs($this->user('admin'));
    }

    public function test_every_page_renders_from_the_database(): void
    {
        $this->get('/')->assertOk()->assertSee('What is BICS?')->assertSee('Leading The');
        $this->get('/gallery')->assertOk()->assertSee('Tanzania 2021')->assertSee('bics2023-12.jpg');
        $this->get('/contact-us')->assertOk()->assertSee('CONTACT FORM');
        $this->get('/media')->assertOk()->assertSee('gellary.jpg');
        foreach (['/bics-overview', '/upcomming-bics', '/library-research'] as $url) {
            $this->get($url)->assertOk()->assertSee('Page Under Construction!!');
        }
        // Footer + event sidebar come from the site-wide page
        $this->get('/')->assertSee('info@beaconpharma.com.bd')->assertSee('window.BICS_EVENT_DATE', false);
    }

    public function test_only_admins_can_use_the_page_builder(): void
    {
        $page = Page::where('slug', 'home')->first();
        $this->get('/cms/pages')->assertRedirect('/login');
        $this->actingAs($this->user('user'))->get('/cms/pages')->assertForbidden();
        $this->actingAs($this->user('user'))->putJson('/cms/sections/'.$page->sections()->first()->id, [])->assertForbidden();

        $this->admin()->get('/cms/pages')->assertOk()->assertSee('Website Pages')->assertSee('Gallery');
        $this->get('/cms/pages/'.$page->id)->assertOk()->assertSee('window.PB', false)->assertSee('page-builder.js');
    }

    public function test_edit_existing_section_content(): void
    {
        $this->admin();
        $about = PageSection::where('type', 'about')->firstOrFail();
        $content = $about->content;
        $content['left_title'] = 'About the Summit';
        $content['right_items'][] = ['text' => 'A brand new objective'];
        $content['unknown_field'] = 'dropped';

        $this->putJson("/cms/sections/{$about->id}", ['label' => 'About', 'content' => $content])
            ->assertOk()->assertJsonPath('section.content.left_title', 'About the Summit');

        $this->assertArrayNotHasKey('unknown_field', $about->fresh()->content);
        $this->get('/')->assertSee('About the Summit')->assertSee('A brand new objective')->assertDontSee('What is BICS?');
    }

    public function test_add_reorder_toggle_duplicate_and_delete_sections(): void
    {
        $this->admin();
        $page = Page::where('slug', 'bics-overview')->firstOrFail();

        // Add a text section and a cards section
        $text = $this->postJson("/cms/pages/{$page->id}/sections", ['type' => 'rich_text'])->assertOk()->json('section');
        $this->putJson($text['urls']['update'], ['content' => ['heading' => 'What is BICS', 'body' => '<p>Summit <strong>details</strong></p>', 'boxed' => true]])->assertOk();
        $cards = $this->postJson("/cms/pages/{$page->id}/sections", ['type' => 'cards'])->assertOk()->json('section');
        $this->putJson($cards['urls']['update'], ['content' => ['heading' => 'Committee', 'columns' => '4', 'items' => [['title' => 'Dr. Smith', 'text' => 'Chair']]]])->assertOk();
        $this->postJson("/cms/pages/{$page->id}/sections", ['type' => 'footer'])->assertStatus(422); // site-wide only

        $html = $this->get('/bics-overview')->assertSee('<strong>details</strong>', false)->assertSee('Dr. Smith')->getContent();
        $this->assertLessThan(strpos($html, 'Dr. Smith'), strpos($html, 'Summit <strong>'));

        // Reorder: cards first
        $old = $page->sections()->where('type', 'rich_text')->where('id', '!=', $text['id'])->first();
        $this->postJson("/cms/pages/{$page->id}/reorder", ['ids' => [$cards['id'], $text['id'], $old->id]])->assertOk();
        $html = $this->get('/bics-overview')->getContent();
        $this->assertLessThan(strpos($html, 'Summit <strong>'), strpos($html, 'Dr. Smith'));

        // Hide the old "under construction" block
        $this->postJson("/cms/sections/{$old->id}/toggle")->assertOk()->assertJsonPath('section.is_active', false);
        $this->get('/bics-overview')->assertDontSee('Page Under Construction!!');

        // Duplicate (copy starts hidden), then delete it
        $copy = $this->postJson("/cms/sections/{$cards['id']}/duplicate")->assertOk()->json('section');
        $this->assertFalse($copy['is_active']);
        $this->deleteJson($copy['urls']['destroy'])->assertOk();
        $this->assertDatabaseMissing('page_sections', ['id' => $copy['id']]);
    }

    public function test_create_custom_page_with_its_own_address(): void
    {
        $this->admin();
        $this->postJson('/cms/pages', ['title' => 'Login'])->assertOk(); // title only; slug becomes login-2
        $this->assertDatabaseHas('pages', ['slug' => 'login-2']);
        $this->postJson('/cms/pages', ['title' => 'X', 'slug' => 'registration'])->assertStatus(422)->assertJsonValidationErrors('slug');
        $this->postJson('/cms/pages', ['title' => 'X', 'slug' => 'Bad Slug!'])->assertStatus(422);

        $redirect = $this->postJson('/cms/pages', ['title' => 'Speakers', 'slug' => 'speakers'])->assertOk()->json('redirect');
        $page = Page::where('slug', 'speakers')->firstOrFail();
        $this->assertStringEndsWith('/cms/pages/'.$page->id, $redirect);

        $section = $this->postJson("/cms/pages/{$page->id}/sections", ['type' => 'cta'])->json('section');
        $this->putJson($section['urls']['update'], ['content' => ['title' => 'Meet our speakers', 'button_text' => 'Register', 'button_url' => 'registration']]);
        $this->get('/speakers')->assertOk()->assertSee('Meet our speakers')->assertSee(url('registration'));

        // Settings: hide -> 404, rename address
        $this->post("/cms/pages/{$page->id}", ['_method' => 'PUT', 'title' => 'Speakers', 'slug' => 'our-speakers', 'meta_title' => 'Speakers 2026', 'is_active' => '1'], ['Accept' => 'application/json'])->assertOk();
        $this->get('/our-speakers')->assertOk()->assertSee('<title>Speakers 2026 | Beacon', false);
        $this->get('/speakers')->assertNotFound();
        $this->post("/cms/pages/{$page->id}", ['_method' => 'PUT', 'title' => 'Speakers', 'slug' => 'our-speakers'], ['Accept' => 'application/json'])->assertOk();
        $this->get('/our-speakers')->assertNotFound();

        // Delete custom page; built-in pages are protected
        $this->deleteJson("/cms/pages/{$page->id}")->assertOk();
        $this->deleteJson('/cms/pages/'.Page::where('slug', 'gallery')->value('id'))->assertForbidden();
        $this->get('/_global')->assertNotFound();
    }

    public function test_site_wide_footer_and_event_date(): void
    {
        $this->admin();
        $global = Page::where('slug', Page::GLOBAL_SLUG)->firstOrFail();
        $footer = $global->sections()->where('type', 'footer')->firstOrFail();
        $content = $footer->content;
        $content['emails'] = "hello@bics.org";
        $content['copyright'] = 'Copyright {year} BICS';
        $this->putJson("/cms/sections/{$footer->id}", ['content' => $content])->assertOk();

        $event = $global->sections()->where('type', 'event_sidebar')->firstOrFail();
        $this->putJson("/cms/sections/{$event->id}", ['content' => array_merge($event->content, ['year_text' => 'Valencia 2026'])])->assertOk();

        // Countdown date now comes from Admin > Event & Registration (with timezone)
        \App\Support\EventSettings::save(['event_start' => '2026-10-28 09:00', 'event_timezone' => 'Europe/Madrid']);

        $this->get('/gallery')->assertSee('mailto:hello@bics.org', false)->assertSee('Copyright '.date('Y').' BICS')
            ->assertDontSee('bics@beaconpharma.com.bd')->assertSee('Valencia 2026')
            ->assertSee('"eventStart":"2026-10-28T09:00:00+01:00"', false);

        // Site-wide sections cannot be hidden or deleted
        $this->postJson("/cms/sections/{$footer->id}/toggle")->assertForbidden();
        $this->deleteJson("/cms/sections/{$footer->id}")->assertForbidden();
    }

    public function test_registration_success_message_is_editable(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        // Registration must be open for this test, whatever the live settings say (rolled back afterwards)
        \App\Support\EventSettings::save(['registration_opens' => '', 'registration_closes' => '']);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $section = PageSection::where('type', 'registration_success')->firstOrFail();
        $this->admin()->get('/cms/pages/' . $section->page_id)->assertOk()->assertSee('Registration success message');

        $content = array_merge($section->content, [
            'title' => 'Thank you for joining {event}!',
            'welcome' => 'Dear {name}, see you in Valencia.',
            'code_label' => 'Your delegate PIN',
            'show_copy' => false,
            'show_download' => false,
            'note' => '',
            'done_label' => 'Back to home',
            'done_url' => '/',
        ]);
        $this->putJson("/cms/sections/{$section->id}", ['content' => $content])->assertOk();

        // AJAX pop-up receives the edited texts
        $this->get('/registration')->assertSee('"code_label":"Your delegate PIN"', false)->assertSee('"done_label":"Back to home"', false)
            ->assertSee('"show_copy":false', false)->assertSee('"done_url_full":"' . str_replace('/', '\/', url('/')) . '"', false);

        // Classic success page uses the same texts; values are escaped
        $this->post('/saveRegisterInfo', [
            'title' => 'Dr', 'firstname' => '<b>Eve</b>', 'lastname' => 'Tester' . uniqid(), 'birthday' => '1980-01-01', 'gender' => 'Female',
            'country' => 'Spain', 'countryCode' => '+34', 'phone' => '612345678', 'email' => uniqid('succ') . '@test.local',
            'profession' => 'Nurse', 'institute' => 'Test',
        ])->assertRedirect('/success');
        $r = \App\Models\Registration::latest('id')->first();
        $html = $this->get('/success')->assertOk()
            ->assertSee('Your delegate PIN')->assertSee('Back to home')->assertSee($r->qr_code)
            ->assertSee('Dear <b>Dr &lt;b&gt;Eve&lt;/b&gt;', false)
            ->assertDontSee('Download card')->getContent();
        $this->assertStringContainsString('Thank you for joining ' . config('bics.event_name'), strip_tags(html_entity_decode($html)));
        File::delete(base_path('upload/userprofile/' . $r->lastname . '-' . $r->qr_code . '.pdf'));

        // Hiding the number removes the ticket
        $this->putJson("/cms/sections/{$section->id}", ['content' => array_merge($content, ['show_code' => false])])->assertOk();
        $this->get('/registration')->assertSee('"show_code":false', false);
    }

    public function test_image_upload(): void
    {
        $this->admin();
        $res = $this->post('/cms/upload', ['file' => UploadedFile::fake()->image('Hero Photo.jpg', 800, 400)], ['Accept' => 'application/json'])
            ->assertOk()->json();
        $this->assertMatchesRegularExpression('#^upload/cms/\d{4}/\d{2}/hero-photo-\w{6}\.jpg$#', $res['path']);
        $this->assertFileExists(base_path($res['path']));
        File::delete(base_path($res['path']));

        $this->post('/cms/upload', ['file' => UploadedFile::fake()->create('evil.php', 1, 'text/plain')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/cms/upload', ['file' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml')], ['Accept' => 'application/json'])->assertStatus(422);
    }
}
