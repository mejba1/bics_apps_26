<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Notifications\EmailNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Public registration form. Runs inside a transaction with mail faked, so nothing is
 * saved and nobody is emailed. Files it creates are removed afterwards.
 */
class RegistrationTest extends TestCase
{
    use DatabaseTransactions;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Tests assume registration is open, whatever the live settings say (rolled back afterwards)
        \App\Support\EventSettings::save(['registration_opens' => '', 'registration_closes' => '']);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) File::delete($file);
        parent::tearDown();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => 'Dr', 'firstname' => 'Test', 'lastname' => 'Delegate' . uniqid(), 'birthday' => '1980-05-20', 'gender' => 'Female',
            'country' => 'Spain', 'countryCode' => '+34', 'phone' => '612 345 678', 'email' => uniqid('delegate') . '@test.local',
            'profession' => 'Medical Doctor', 'specialty' => 'Medical Oncologist', 'institute' => 'Test Hospital',
        ], $override);
    }

    private function track(Registration $r): void
    {
        $this->files[] = base_path('upload/userprofile/' . $r->lastname . '-' . $r->qr_code . '.pdf');
        if ($r->image !== 'no-photo.png') $this->files[] = base_path('upload/images/' . $r->image);
    }

    public function test_page_shows_the_new_form(): void
    {
        $this->get('/registration')->assertOk()
            ->assertSee('id="regForm"', false)->assertSee(config('bics.event_name'))
            ->assertSee('data-dial="+34"', false)->assertSee('registration.js');
    }

    public function test_ajax_registration_with_photo_returns_json_and_saves_everything(): void
    {
        $data = $this->payload(['image' => UploadedFile::fake()->image('My Photo (1).jpg', 600, 600)]);
        $res = $this->post('/saveRegisterInfo', $data, ['Accept' => 'application/json'])->assertOk()
            ->assertJsonStructure(['success', 'code', 'name', 'email', 'email_sent', 'message', 'card_url'])
            ->assertJson(['success' => true, 'email_sent' => true]);

        $r = Registration::where('qr_code', $res->json('code'))->firstOrFail();
        $this->track($r);
        $this->assertSame('+34-612345678', $r->phone);
        $this->assertSame('Yes', $r->email_notification);
        $this->assertMatchesRegularExpression('/^\d{6}\.my-photo-1\.jpg$/', $r->image);
        $this->assertFileExists(base_path('upload/images/' . $r->image));
        $this->assertFileExists(base_path('upload/userprofile/' . $r->lastname . '-' . $r->qr_code . '.pdf'));
        $this->assertDatabaseHas('attendees', ['register_id' => $r->id, 'qr_code' => $r->qr_code, 'venue' => config('bics.venue')]);
        $this->assertDatabaseHas('register_accounts', ['register_id' => $r->id]);
        Notification::assertSentTo($r, EmailNotification::class);
    }

    public function test_classic_form_post_still_redirects_to_success_page(): void
    {
        $this->post('/saveRegisterInfo', $this->payload())->assertRedirect('/success');
        $r = Registration::latest('id')->first();
        $this->track($r);
        $this->assertSame('no-photo.png', $r->image);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $email = uniqid('dup') . '@test.local';
        $first = $this->post('/saveRegisterInfo', $this->payload(['email' => $email]), ['Accept' => 'application/json'])->assertOk();
        $this->track(Registration::where('qr_code', $first->json('code'))->first());

        $this->post('/saveRegisterInfo', $this->payload(['email' => strtoupper($email)]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_validation_and_spam_trap(): void
    {
        $this->post('/saveRegisterInfo', $this->payload(['birthday' => now()->addDay()->toDateString(), 'phone' => 'call me', 'gender' => 'x', 'profession' => '']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['birthday', 'phone', 'gender', 'profession']);

        $this->post('/saveRegisterInfo', $this->payload(['image' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf')]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('image');

        $before = Registration::count();
        $this->post('/saveRegisterInfo', $this->payload(['reg_hp_check' => 'http://spam.example']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('form');
        $this->assertSame($before, Registration::count());

        // Browsers autofill fields named "website" - that must never block a real person
        $this->post('/saveRegisterInfo', $this->payload(['website' => 'https://my-hospital.org']), ['Accept' => 'application/json'])->assertOk();
        $this->track(Registration::latest('id')->first());
    }
}
