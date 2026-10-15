<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\Role;
use App\Models\User;
use App\Support\EventSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Admin registration form, PDF reports and dashboard cards. Transaction + mail faked. */
class AdminRegistrationReportsTest extends TestCase
{
    use DatabaseTransactions;

    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        EventSettings::forget();
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $code) {
            $r = Registration::where('qr_code', $code)->first();
            if (!$r) continue;
            File::delete(base_path('upload/userprofile/' . $r->lastname . '-' . $r->qr_code . '.pdf'));
            if ($r->image !== 'no-photo.png') File::delete(base_path('upload/images/' . $r->image));
        }
        EventSettings::forget();
        parent::tearDown();
    }

    private function user(array $permissions, bool $super = false): User
    {
        $role = $super ? Role::where('is_super', true)->first() : tap(Role::create(['name' => 'T' . uniqid()]))->syncPermissions($permissions);
        $u = new User();
        $u->forceFill(['name' => 'Tester', 'email' => uniqid('t') . '@test.local', 'password' => bcrypt('x-secret-1'), 'role' => $super ? 'admin' : 'user', 'role_id' => $role->id, 'status' => 'active'])->save();
        return $u;
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'title' => 'Dr', 'firstname' => 'Onsite', 'lastname' => 'Guest' . uniqid(), 'birthday' => '1975-03-04', 'gender' => 'Female',
            'country' => 'Spain', 'phone' => '600111222', 'email' => uniqid('onsite') . '@test.local',
            'profession' => 'Medical Doctor', 'specialty' => 'Medical Oncologist', 'institute' => 'Hospital La Fe',
        ], $o);
    }

    public function test_admin_registration_form(): void
    {
        $this->get('/registrations/new')->assertRedirect('/login');
        $this->actingAs($this->user(['registrations.view']))->get('/registrations/new')->assertForbidden();

        $this->actingAs($this->user(['registrations.create', 'registrations.export']));
        $this->get('/registrations/new')->assertOk()->assertSee('New registration')->assertSee('Mark as checked in')->assertSee('data-dial="+34"', false);

        // Works even when public registration is closed; no email, checked in, with a large photo
        EventSettings::save(['registration_opens' => '', 'registration_closes' => now('Europe/Madrid')->subDay()->format('Y-m-d H:i')]);
        $res = $this->post('/registrations', $this->payload([
            'send_email' => '0', 'checked_in' => '1', 'image' => UploadedFile::fake()->image('camera.jpg', 3000, 2000),
        ]), ['Accept' => 'application/json'])->assertOk()->assertJson(['success' => true, 'email_sent' => false, 'checked_in' => true]);
        $this->created[] = $res->json('code');
        $r = Registration::where('qr_code', $res->json('code'))->firstOrFail();
        $this->assertSame('Yes', $r->verify_status);
        $this->assertSame('+34-600111222', $r->phone);
        [$w, $h] = getimagesize(base_path('upload/images/' . $r->image));
        $this->assertLessThanOrEqual(800, max($w, $h));  // photo shrunk
        $this->assertNotNull($res->json('admin_card_url'));
        Notification::assertNothingSent();

        // With email
        $res = $this->post('/registrations', $this->payload(['send_email' => '1']), ['Accept' => 'application/json'])->assertOk()->assertJson(['email_sent' => true]);
        $this->created[] = $res->json('code');
        Notification::assertSentTo(Registration::where('qr_code', $res->json('code'))->first(), \App\Notifications\EmailNotification::class);

        // Same rules as the public form
        $email = Registration::where('qr_code', $res->json('code'))->value('email');
        $this->post('/registrations', $this->payload(['email' => $email]), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->post('/registrations', $this->payload(['country' => 'Narnia', 'phone' => 'abc']), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['country', 'phone']);
    }

    public function test_pdf_reports(): void
    {
        $this->actingAs($this->user([], true));
        $venue = DB::table('registrations')->value('venue');
        $country = DB::table('registrations')->where('venue', $venue)->value('country');
        foreach (['/createPdf', '/createPdfByVenue/' . $venue, '/country-wise-report/' . $country, '/country-wise-report/' . $country . '/' . $venue] as $url) {
            $res = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $res->getContent(), $url);
            $this->assertStringContainsString('attachment; filename="BICS-registrations', $res->headers->get('Content-Disposition'));
        }
        $id = DB::table('registrations')->value('id');
        $this->get('/download-profile-Pdf/' . $id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/download-profile-Pdf/999999999')->assertNotFound();

        // Report summary numbers
        $rows = DB::table('registrations')->where('venue', $venue)->get();
        $s = \App\Support\RegistrationReport::summary($rows);
        $this->assertSame($rows->count(), $s['total']);
        $this->assertSame($rows->where('gender', 'Male')->count(), $s['male']);
    }

    public function test_dashboard_gender_and_profession_cards(): void
    {
        $this->actingAs($this->user([], true));
        $html = $this->get('/home?venue=all')->assertOk()->assertSee("Who's attending", false)->getContent();
        $male = DB::table('registrations')->where('gender', 'Male')->count();
        $this->assertMatchesRegularExpression('#<div class="db-kpi-value">' . $male . '</div><div class="db-kpi-label">Male#', $html);
        $doctors = DB::table('registrations')->where('profession', 'Medical Doctor')->count();
        $this->assertStringContainsString('title="Medical Doctor">Medical Doctor</div>', $html);
        $this->assertMatchesRegularExpression('#title="Medical Doctor">Medical Doctor</div>\s*<div class="db-prof-value">' . $doctors . ' #', $html);
    }
}
