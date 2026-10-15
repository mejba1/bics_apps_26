<?php

namespace Tests\Feature;

use App\Models\FormOption;
use App\Models\Registration;
use App\Models\User;
use App\Support\EventSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Admin > Form Options and Admin > Event & Registration.
 * Runs inside a transaction with mail faked, so nothing is saved and nobody is emailed.
 */
class RegistrationSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        EventSettings::forget();
        // Tests assume registration is open, whatever the live settings say (rolled back afterwards)
        EventSettings::save(['registration_opens' => '', 'registration_closes' => '']);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        EventSettings::forget();
        parent::tearDown();
    }

    private function admin(): self
    {
        $u = new User();
        $u->forceFill(['name' => 'Admin', 'email' => uniqid('a') . '@test.local', 'password' => bcrypt('secret-pass'), 'role' => 'admin'])->save();
        return $this->actingAs($u);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'title' => 'Dr', 'firstname' => 'Test', 'lastname' => 'Win' . uniqid(), 'birthday' => '1980-05-20', 'gender' => 'Male',
            'country' => 'Spain', 'countryCode' => '+99', 'phone' => '612345678', 'email' => uniqid('w') . '@test.local',
            'profession' => 'Medical Doctor', 'specialty' => 'Medical Oncologist', 'institute' => 'Test',
        ], $o);
    }

    private function cleanup(): void
    {
        foreach (Registration::where('email', 'like', '%@test.local')->get() as $r) {
            File::delete(base_path('upload/userprofile/' . $r->lastname . '-' . $r->qr_code . '.pdf'));
        }
    }

    public function test_admin_pages_are_admin_only(): void
    {
        $this->get('/form-options')->assertRedirect('/login');
        $this->get('/event-settings')->assertRedirect('/login');
        $user = new User();
        $user->forceFill(['name' => 'U', 'email' => uniqid('u') . '@test.local', 'password' => bcrypt('x'), 'role' => 'user'])->save();
        $this->actingAs($user)->get('/form-options')->assertForbidden();
        $this->admin()->get('/form-options')->assertOk()->assertSee('Professions &amp; specialities', false)->assertSee('form-options.js');
        $this->get('/event-settings')->assertOk()->assertSee('Registration window')->assertSee('Europe/Madrid');
    }

    public function test_form_options_crud_drives_the_registration_form(): void
    {
        $this->admin();

        // Add a profession with a speciality, and a title
        $prof = $this->postJson('/form-options', ['list' => 'profession', 'label' => 'Radiologist'])->assertOk()->json('option');
        $this->postJson('/form-options', ['list' => 'speciality', 'parent_id' => $prof['id'], 'label' => 'Interventional Radiologist'])->assertOk();
        $this->postJson('/form-options', ['list' => 'title', 'label' => 'Prof Dr'])->assertOk();
        $this->postJson('/form-options', ['list' => 'title', 'label' => 'Dr'])->assertStatus(422)->assertJsonValidationErrors('label'); // duplicate
        $this->postJson('/form-options', ['list' => 'speciality', 'label' => 'Orphan'])->assertStatus(422)->assertJsonValidationErrors('parent_id');

        // New country with dial code (the + is added automatically)
        $country = $this->postJson('/form-options', ['list' => 'country', 'label' => 'Atlantis', 'extra' => '999'])->assertOk()->json('option');
        $this->assertSame('+999', $country['extra']);
        $this->postJson('/form-options', ['list' => 'country', 'label' => 'Nowhere', 'extra' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('extra');

        $html = $this->get('/registration')->assertOk()->getContent();
        $this->assertStringContainsString('>Radiologist</option>', $html);
        $this->assertStringContainsString('Interventional Radiologist', $html);
        $this->assertStringContainsString('value="Prof Dr"', $html);
        $this->assertStringContainsString('data-dial="+999"', $html);

        // Rename + hide
        $nurse = FormOption::where('list', 'profession')->where('label', 'Nurse')->firstOrFail();
        $this->putJson("/form-options/{$nurse->id}", ['label' => 'Nurse / Midwife'])->assertOk();
        $this->postJson("/form-options/{$country['id']}/toggle")->assertOk()->assertJsonPath('option.is_active', false);
        $html = $this->get('/registration')->getContent();
        $this->assertStringContainsString('>Nurse / Midwife</option>', $html);
        $this->assertStringNotContainsString('Atlantis', $html);

        // Reorder titles: Ms first
        $titles = FormOption::where('list', 'title')->orderBy('sort')->pluck('id', 'label');
        $ids = $titles->values()->all();
        $ms = $titles['Ms'];
        $this->postJson('/form-options/reorder', ['ids' => array_merge([$ms], array_values(array_diff($ids, [$ms])))])->assertOk();
        $this->assertSame('Ms', FormOption::active('title')->first()->label);

        // Delete a profession removes its specialities
        $this->deleteJson("/form-options/{$prof['id']}")->assertOk()->assertJsonPath('removed_children', 1);
        $this->assertDatabaseMissing('form_options', ['label' => 'Interventional Radiologist']);
    }

    public function test_server_only_accepts_listed_values_and_uses_admin_dial_code(): void
    {
        // Values not in the lists are rejected
        $this->postJson('/saveRegisterInfo', $this->payload(['country' => 'Narnia', 'title' => 'King', 'profession' => 'Wizard']))
            ->assertStatus(422)->assertJsonValidationErrors(['country', 'title', 'profession']);
        $this->postJson('/saveRegisterInfo', $this->payload(['specialty' => 'Hospital Pharmacist'])) // belongs to Pharmacist
            ->assertStatus(422)->assertJsonValidationErrors('specialty');

        // Hidden values are rejected too
        FormOption::where('list', 'title')->where('label', 'Mr')->update(['is_active' => false]);
        $this->postJson('/saveRegisterInfo', $this->payload(['title' => 'Mr']))->assertStatus(422)->assertJsonValidationErrors('title');

        // Valid: dial code comes from the country list, not from the browser
        $code = $this->postJson('/saveRegisterInfo', $this->payload())->assertOk()->json('code');
        $this->assertSame('+34-612345678', Registration::where('qr_code', $code)->value('phone'));
        $this->cleanup();
    }

    public function test_event_settings_and_registration_window(): void
    {
        $this->admin();
        $base = [
            'event_name' => 'BICS Test Summit', 'event_short' => 'BICS 2027', 'event_venue' => 'Test Hall, Madrid', 'event_timezone' => 'Europe/Madrid',
            'event_start' => '2027-03-10T09:00', 'event_end' => '2027-03-12T18:00', 'registration_opens' => '', 'registration_closes' => '',
            'registration_closed_message' => 'Sorry, we are full.', 'registration_upcoming_message' => 'Opening soon!',
            'venue_code' => 'Madrid-27', 'tracking_no' => 'Madrid-20270310',
        ];

        // Validation
        $this->putJson('/event-settings', array_merge($base, ['event_end' => '2027-03-01T09:00', 'venue_code' => 'bad code', 'event_timezone' => 'Mars/Base']))
            ->assertStatus(422)->assertJsonValidationErrors(['event_end', 'venue_code', 'event_timezone']);
        $this->putJson('/event-settings', array_merge($base, ['registration_opens' => '2027-01-02T00:00', 'registration_closes' => '2027-01-01T00:00']))
            ->assertStatus(422)->assertJsonValidationErrors('registration_closes');

        // Save: shown on home, registration page, and the countdown uses the event timezone
        $this->putJson('/event-settings', $base)->assertOk()->assertJsonPath('status.state', 'open')->assertJsonPath('status.event', '10–12 March 2027');
        $this->get('/')->assertSee('BICS Test Summit')->assertSee('10–12 March 2027')->assertSee('data-evc-target="2027-03-10T09:00:00+01:00"', false);
        $this->get('/registration')->assertSee('Test Hall, Madrid')->assertSee('id="regForm"', false);

        // Not open yet: form replaced by countdown card; server refuses
        $this->putJson('/event-settings', array_merge($base, ['registration_opens' => now('Europe/Madrid')->addDays(3)->format('Y-m-d\TH:i')]))
            ->assertOk()->assertJsonPath('status.state', 'upcoming');
        $this->get('/registration')->assertSee('Registration opens soon')->assertSee('Opening soon!')->assertDontSee('id="regForm"', false);
        $this->get('/')->assertSee('Registration opens');
        $this->postJson('/saveRegisterInfo', $this->payload())->assertStatus(422)->assertJsonValidationErrors('form');

        // Closed
        $this->putJson('/event-settings', array_merge($base, ['registration_closes' => now('Europe/Madrid')->subHour()->format('Y-m-d\TH:i')]))
            ->assertOk()->assertJsonPath('status.state', 'closed');
        $this->get('/registration')->assertSee('Registration closed')->assertSee('Sorry, we are full.');
        $this->postJson('/saveRegisterInfo', $this->payload())->assertStatus(422)->assertJsonFragment(['form' => ['Sorry, we are full.']]);

        // Open with a deadline: deadline shown; new registrations use the new venue code
        $this->putJson('/event-settings', array_merge($base, ['registration_closes' => now('Europe/Madrid')->addDays(10)->format('Y-m-d\TH:i')]))->assertOk();
        $this->get('/registration')->assertSee('Registration closes');
        $code = $this->postJson('/saveRegisterInfo', $this->payload())->assertOk()->json('code');
        $this->assertSame('Madrid-27', Registration::where('qr_code', $code)->value('venue'));
        $this->cleanup();
    }
}
