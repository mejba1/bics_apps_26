<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\Role;
use App\Models\User;
use App\Notifications\EmailNotification;
use App\Support\EmailTemplate;
use App\Support\EventSettings;
use App\Support\PinnedEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Admin > Email Template, pinned event, Reports page. */
class EmailTemplateReportsTest extends TestCase
{
    use DatabaseTransactions;

    private ?string $banner = null;

    protected function setUp(): void
    {
        parent::setUp();
        EmailTemplate::forget();
        EventSettings::forget();
    }

    protected function tearDown(): void
    {
        if ($this->banner) File::delete(base_path($this->banner));
        EmailTemplate::forget();
        parent::tearDown();
    }

    private function user(array $permissions = [], bool $super = true): User
    {
        $role = $super ? Role::where('is_super', true)->first() : tap(Role::create(['name' => 'T' . uniqid()]))->syncPermissions($permissions);
        $u = new User();
        $u->forceFill(['name' => 'Tester', 'email' => uniqid('t') . '@test.local', 'password' => bcrypt('x-secret-1'), 'role' => $super ? 'admin' : 'user', 'role_id' => $role->id, 'status' => 'active'])->save();
        $this->actingAs($u);
        return $u;
    }

    private function mail(): \Illuminate\Notifications\Messages\MailMessage
    {
        $r = new Registration(); $r->firstname = 'Ayesha';
        return (new EmailNotification(['pin' => '123456', 'name' => 'Rahman', 'title' => 'Dr', 'profession' => 'Nurse', 'qrcode' => 'upload/userprofile/Rahman-123456.pdf']))->toMail($r);
    }

    public function test_email_template_editing(): void
    {
        $this->get('/email-template')->assertRedirect('/login');
        $this->user(['registrations.view'], false);
        $this->get('/email-template')->assertForbidden();

        $this->user();
        $this->get('/email-template')->assertOk()->assertSee('Placeholders')->assertSee('{first_name}')->assertSee('CONFIRMATION EMAIL ATTACHMENTS');

        // Defaults = the original email
        $mail = $this->mail();
        $this->assertSame('Registration confirmed – ' . EventSettings::get('event_short') . ' · No. 123456', $mail->subject);

        // Validation
        $this->putJson('/email-template', ['subject' => '', 'brand_color' => 'red'])->assertStatus(422)->assertJsonValidationErrors(['subject', 'brand_color']);

        // Save new texts (with placeholders, bold, HTML that must be escaped) and switch sections off
        $this->putJson('/email-template', [
            'subject' => 'Welcome {first_name}! Your {event_short} number is {pin}',
            'heading' => 'Hello {full_name}', 'intro' => "Thanks **{first_name}** <script>alert(1)</script>\nSecond line",
            'pin_label' => 'Your PIN', 'steps_title' => 'Next', 'steps' => "{attachments}\nCome early", 'closing' => 'Bye',
            'button_text' => 'Get card', 'footer_note' => 'By Beacon', 'brand_color' => '#123456', 'accent_color' => '#abcdef',
            'show_event' => '0', 'show_button' => '1', 'show_banner' => '0',
        ])->assertOk();

        $mail = $this->mail();
        $this->assertSame('Welcome Ayesha! Your ' . EventSettings::get('event_short') . ' number is 123456', $mail->subject);
        $html = view($mail->view, $mail->viewData)->render();
        $this->assertStringContainsString('Hello Dr Ayesha Rahman', $html);
        $this->assertStringContainsString('Thanks <strong style="color:#1d2433;">Ayesha</strong> &lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('Second line', $html);
        $this->assertStringContainsString('Your PIN', $html);
        $this->assertStringContainsString('Get card', $html);
        $this->assertStringContainsString('Come early', $html);
        $this->assertStringContainsString('#123456', $html);
        $this->assertStringNotContainsString('&#128205; Venue', $html);     // event box off
        $this->assertStringNotContainsString('bics-email-banner', $html);   // banner off

        // Live preview of unsaved values
        $this->post('/email-template/preview', ['subject' => 'Draft {pin}', 'heading' => 'Draft heading', 'steps' => 'x'])->assertOk()->assertSee('Draft heading')->assertSee('<title>Draft 484704</title>', false);

        // Banner upload
        $res = $this->post('/email-template/banner', ['banner' => UploadedFile::fake()->image('b.jpg', 1200, 400)], ['Accept' => 'application/json'])->assertOk();
        $this->banner = $res->json('banner');
        $this->assertFileExists(base_path($this->banner));
        EmailTemplate::forget();
        $this->assertSame('1', EmailTemplate::get('show_banner'));

        // Test email
        Notification::fake();
        $this->postJson('/email-template/test', ['email' => 'not-an-email'])->assertStatus(422);
        $this->postJson('/email-template/test', ['email' => 'me@test.local'])->assertOk();
        Notification::assertSentOnDemand(EmailNotification::class);

        // Restore original
        $this->postJson('/email-template/reset')->assertOk()->assertJsonPath('tpl.subject', EmailTemplate::DEFAULTS['subject']);
        $this->assertSame('Registration confirmed – ' . EventSettings::get('event_short') . ' · No. 123456', $this->mail()->subject);
    }

    public function test_pin_event_and_reports(): void
    {
        $u = $this->user();
        $current = PinnedEvent::current();
        $other = DB::table('registrations')->where('venue', '!=', $current)->value('venue');
        $this->assertNotNull($other);

        // Default: current event
        $this->assertSame($current, PinnedEvent::get($u->id));
        $this->get('/home')->assertOk()->assertSee('Pinned');

        // Pin another event: dashboard + reports follow
        $this->postJson('/pin-event', ['venue' => 'Nowhere-99'])->assertStatus(422);
        $this->postJson('/pin-event', ['venue' => $other])->assertOk()->assertJsonPath('pinned', $other);
        $this->assertSame($other, PinnedEvent::get($u->id));
        $html = $this->get('/home')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#db-venue is-active[^>]*>\s*<i class="bi bi-pin-angle-fill db-pin"[^>]*></i>\s*' . preg_quote($other, '#') . '#', $html);
        $this->get('/reports')->assertOk()->assertSee('Reports are for your pinned event')->assertSee('Past event');

        $res = $this->get('/reports/download?venue=pinned')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('BICS-registrations-' . $other, $res->headers->get('Content-Disposition'));
        $res = $this->get('/reports/download?venue=pinned&status=checked_in')->assertOk();
        $this->assertStringContainsString('-checked-in-', $res->headers->get('Content-Disposition'));
        $country = DB::table('registrations')->where('venue', $other)->value('country');
        $this->get('/reports/download?venue=pinned&country=' . urlencode($country))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $res = $this->get('/reports/download?venue=all')->assertOk();
        $this->assertStringContainsString('filename="BICS-registrations-' . now()->format('Y'), $res->headers->get('Content-Disposition'));
        $this->get('/reports/download?status=bogus')->assertStatus(302);

        // Pins are per user
        $mine = $u->id;
        $this->user();
        $this->assertSame($current, PinnedEvent::get());
        $this->assertSame($other, PinnedEvent::get($mine));

        // Pinning the current event = back to default
        $this->actingAs(User::find($mine))->post('/pin-event', ['venue' => $current])->assertRedirect();
        $this->assertSame($current, PinnedEvent::get($mine));

        // Report builder: grouped summaries (preview, PDF, CSV) with filters
        $all = DB::table('registrations')->count();
        $res = $this->getJson('/reports/preview?venue=all&by=year')->assertOk();
        $this->assertSame($all, $res->json('totals.total'));
        $this->assertSame(DB::table('registrations')->selectRaw('YEAR(created_at) y')->distinct()->count('created_at') > 0, count($res->json('groups')) > 0);
        $years = collect($res->json('groups'))->pluck('label')->all();
        $this->assertSame($years, collect($years)->sort()->values()->all()); // chronological
        $res = $this->getJson('/reports/preview?venue=all&by=gender')->assertOk();
        $this->assertSame(DB::table('registrations')->where('gender', 'Male')->count(), $res->json('totals.male'));
        $res = $this->getJson('/reports/preview?venue=' . urlencode($other) . '&by=country&gender=Female')->assertOk();
        $this->assertSame(DB::table('registrations')->where('venue', $other)->where('gender', 'Female')->count(), $res->json('totals.total'));
        $this->assertStringContainsString('Female', $res->json('scope'));
        $res = $this->getJson('/reports/preview?venue=all&by=date&from=2024-01-01&to=2024-12-31')->assertOk();
        $this->assertSame(DB::table('registrations')->whereYear('created_at', 2024)->count(), $res->json('totals.total'));
        $this->getJson('/reports/preview?from=2025-02-01&to=2025-01-01')->assertStatus(422)->assertJsonValidationErrors('to');
        $this->getJson('/reports/preview?by=bogus')->assertStatus(422);

        foreach (['date', 'month', 'year', 'venue', 'gender', 'country', 'profession', 'status'] as $by) {
            $res = $this->get('/reports/download?type=summary&venue=all&by=' . $by)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringContainsString('summary-by-' . $by, $res->headers->get('Content-Disposition'));
        }
        $csv = $this->get('/reports/download?type=summary&by=venue&venue=all&format=csv')->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $body = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF" . 'Event,Registrations,Male,Female', $body);
        $this->assertStringContainsString($other . ',', $body);
        $list = $this->get('/reports/download?venue=all&format=csv&year=2023')->assertOk()->streamedContent();
        $this->assertSame(DB::table('registrations')->whereYear('created_at', 2023)->count() + 1, count(array_filter(explode("\n", trim($list)))) - substr_count(trim($list), "\n\n"));
        $this->get('/reports/download?venue=all&type=list&gender=Male&year=2023')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/reports')->assertOk()->assertSee('Report builder')->assertSee('Year-wise')->assertSee('Country-wise');

        // Staff without export cannot see reports
        $this->user(['registrations.view'], false);
        $this->get('/reports')->assertForbidden();
    }
}
