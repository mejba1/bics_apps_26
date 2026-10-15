<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\Role;
use App\Models\User;
use App\Notifications\EmailNotification;
use App\Support\IdCard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Sending the confirmation email from the admin panel, and the ID card. Mail is faked; card files are restored. */
class AttendeeEmailIdCardTest extends TestCase
{
    use DatabaseTransactions;

    private array $backups = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        // Put back the card files these tests regenerated
        foreach ($this->backups as $path => $content) {
            $content === null ? File::delete(base_path($path)) : File::put(base_path($path), $content);
        }
        parent::tearDown();
    }

    private function keep(Registration $r): void
    {
        $p = IdCard::path($r);
        if (!array_key_exists($p, $this->backups)) $this->backups[$p] = is_file(base_path($p)) ? File::get(base_path($p)) : null;
    }

    private function user(array $permissions = [], bool $super = true): User
    {
        $role = $super ? Role::where('is_super', true)->first() : tap(Role::create(['name' => 'T' . uniqid()]))->syncPermissions($permissions);
        $u = new User();
        $u->forceFill(['name' => 'Tester', 'email' => uniqid('t') . '@test.local', 'password' => bcrypt('x-secret-1'), 'role' => $super ? 'admin' : 'user', 'role_id' => $role->id, 'status' => 'active'])->save();
        $this->actingAs($u);
        return $u;
    }

    public function test_send_email_to_one_attendee(): void
    {
        $r = Registration::where('email_notification', 'Yes')->get()->first(fn ($x) => filter_var($x->email, FILTER_VALIDATE_EMAIL));
        $this->keep($r);

        $this->user(['registrations.view'], false);
        $this->postJson("/registrations/{$r->id}/send-email", ['email' => $r->email])->assertForbidden();

        $this->user();
        // Force re-send to the same address (already sent before)
        $this->postJson("/registrations/{$r->id}/send-email", ['email' => $r->email])->assertOk()->assertJson(['success' => true]);
        Notification::assertSentTo($r, EmailNotification::class);
        $this->assertFileExists(base_path(IdCard::path($r)));

        // Send to a different address without changing the record
        $this->postJson("/registrations/{$r->id}/send-email", ['email' => 'Other.Address@Test.local', 'save_email' => 0])->assertOk();
        Notification::assertSentTo(new AnonymousNotifiable, EmailNotification::class, fn ($n, $ch, $notifiable) => $notifiable->routes['mail'] === 'other.address@test.local');
        $this->assertNotSame('other.address@test.local', $r->fresh()->email);

        // ...or correct the address on the record
        $this->postJson("/registrations/{$r->id}/send-email", ['email' => 'fixed.address@test.local', 'save_email' => 1])->assertOk()->assertJsonPath('email', 'fixed.address@test.local');
        $this->assertSame('fixed.address@test.local', $r->fresh()->email);

        // Validation and duplicates within the same event
        $this->postJson("/registrations/{$r->id}/send-email", ['email' => 'nope'])->assertStatus(422);
        $other = Registration::where('venue', $r->venue)->where('id', '!=', $r->id)->get()
            ->first(fn ($x) => filter_var($x->email, FILTER_VALIDATE_EMAIL) && strtolower($x->email) !== 'fixed.address@test.local');
        $this->postJson("/registrations/{$r->id}/send-email", ['email' => $other->email, 'save_email' => 1])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_send_to_everyone_not_sent_in_batches(): void
    {
        $this->user();
        $venue = 'Algeria';
        $ids = Registration::where('venue', $venue)->orderBy('id')->limit(20)->pluck('id');
        DB::table('registrations')->where('venue', $venue)->update(['email_notification' => 'Yes']);
        DB::table('registrations')->whereIn('id', $ids)->update(['email_notification' => 'No']);
        Registration::whereIn('id', $ids)->get()->each(fn ($r) => $this->keep($r));

        $res = $this->postJson('/registrations/send-pending', ['venue' => $venue])->assertOk();
        $this->assertSame(15, $res->json('sent') + count($res->json('failed')));
        $this->assertSame(5, $res->json('remaining'));
        $res = $this->postJson('/registrations/send-pending', ['venue' => $venue, 'after_id' => $res->json('after_id')])->assertOk();
        $this->assertSame(0, $res->json('remaining'));
        $this->assertSame(0, Registration::where('venue', $venue)->where('email_notification', '!=', 'Yes')->count());
        Notification::assertSentTimes(EmailNotification::class, 20);

        // Dashboard shows the buttons
        DB::table('registrations')->where('id', $ids[0])->update(['email_notification' => 'No']);
        $this->get('/home?venue=' . $venue)->assertOk()->assertSee('Email 1 not sent')->assertSee('sendEmail', false);
    }

    public function test_id_card(): void
    {
        $this->user();
        $r = Registration::where('image', '!=', 'no-photo.png')->firstOrFail();
        $res = $this->get("/registrations/{$r->id}/id-card")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());

        $data = IdCard::viewData($r);
        $this->assertSame($r->qr_code, $data['data']->qr_code);
        $this->assertStringStartsWith('data:image/png;base64,', $data['header']);
        $this->assertSame([17, 1], IdCard::fitName('Dr Ana Ruiz'));
        $this->assertSame(2, IdCard::fitName('Professor Mohammad Abdullah Al Mamun Chowdhury')[1]);
        $this->assertSame('PHARMACIST', IdCard::role('Pharmacist')['label']);
        $this->assertSame('DELEGATE', IdCard::role(null)['label']);

        // Card PDF is a single A6 page
        $pdf = IdCard::pdf($r)->output();
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf));
        $this->assertMatchesRegularExpression('#/MediaBox\s*\[\s*0\.?0*\s+0\.?0*\s+297\.6\d*\s+419\.5\d*\s*\]#', $pdf);
    }
}
