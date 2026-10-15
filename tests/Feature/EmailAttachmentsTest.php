<?php

namespace Tests\Feature;

use App\Models\EmailAttachment;
use App\Models\Registration;
use App\Models\Role;
use App\Models\User;
use App\Notifications\EmailNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Admin > Event & Registration > Confirmation email attachments. */
class EmailAttachmentsTest extends TestCase
{
    use DatabaseTransactions;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $f) File::delete(base_path($f));
        parent::tearDown();
    }

    private function admin(): self
    {
        $u = new User();
        $u->forceFill(['name' => 'Tester', 'email' => uniqid('t') . '@test.local', 'password' => bcrypt('x-secret-1'), 'role' => 'admin',
            'role_id' => Role::where('is_super', true)->value('id'), 'status' => 'active'])->save();
        return $this->actingAs($u);
    }

    private function mail(): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new EmailNotification(['pin' => '123456', 'name' => 'Doe', 'title' => 'Dr', 'profession' => 'Nurse', 'qrcode' => 'upload/userprofile/Doe-123456.pdf']))
            ->toMail(new Registration());
    }

    public function test_manage_attachments_and_they_are_sent(): void
    {
        $this->postJson('/email-template/attachments')->assertUnauthorized();
        EmailAttachment::query()->update(['is_active' => false]);
        $this->admin();

        // Validation: name + PDF/image only
        $this->post('/email-template/attachments', ['title' => '', 'file' => UploadedFile::fake()->create('x.exe', 10)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['title', 'file']);

        // Upload an itinerary
        $res = $this->post('/email-template/attachments', ['title' => 'Itinerary', 'file' => UploadedFile::fake()->create('plan.pdf', 300, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('item.title', 'Itinerary')->assertJsonPath('item.is_active', true);
        $it = EmailAttachment::findOrFail($res->json('item.id'));
        $this->files[] = $it->path;
        $this->assertFileExists($it->fullPath());
        $this->assertStringEndsWith('-itinerary.pdf', strtolower($it->downloadName()));

        // Settings page lists it; the email attaches it and names it
        $this->get('/email-template')->assertOk()->assertSee('CONFIRMATION EMAIL ATTACHMENTS')->assertSee('Itinerary');
        $mail = $this->mail();
        $this->assertCount(1, $mail->attachments);
        $this->assertSame($it->downloadName(), $mail->attachments[0]['options']['as']);
        $html = view($mail->view, $mail->viewData)->render();
        $this->assertStringContainsString('Your <strong>Itinerary</strong> is attached', $html);

        // Second file → both attached, in order
        $res = $this->post('/email-template/attachments', ['title' => 'Venue map', 'file' => UploadedFile::fake()->image('map.png', 400, 300)], ['Accept' => 'application/json'])->assertOk();
        $map = EmailAttachment::findOrFail($res->json('item.id'));
        $this->files[] = $map->path;
        $this->postJson('/email-template/attachments/order', ['ids' => [$map->id, $it->id]])->assertOk();
        $mail = $this->mail();
        $this->assertSame([$map->downloadName(), $it->downloadName()], array_map(fn ($a) => $a['options']['as'], $mail->attachments));
        $this->assertStringContainsString('Attached to this email: <strong>Venue map</strong> and <strong>Itinerary</strong>.', view($mail->view, $mail->viewData)->render());

        // Rename, switch off, replace
        $this->post("/email-template/attachments/{$map->id}", ['title' => 'Venue map 2026', 'is_active' => 0], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('item.is_active', false);
        $this->assertCount(1, $this->mail()->attachments);
        $old = $it->fullPath();
        $this->post("/email-template/attachments/{$it->id}", ['file' => UploadedFile::fake()->create('plan-v2.pdf', 200, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $it->refresh(); $this->files[] = $it->path;
        $this->assertFileDoesNotExist($old);
        $this->assertSame('plan-v2.pdf', $it->original_name);

        // Too large in total (limit protects delivery)
        $it->update(['size' => EmailAttachment::MAX_TOTAL_MB * 1048576]);
        $this->post('/email-template/attachments', ['title' => 'Extra', 'file' => UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');

        // A file missing on disk is skipped, never blocks the email
        File::delete($it->fullPath());
        $this->assertCount(0, $this->mail()->attachments);

        // Delete
        $this->deleteJson("/email-template/attachments/{$map->id}")->assertOk();
        $this->assertModelMissing($map);
        $this->assertFileDoesNotExist($map->fullPath());
    }

    public function test_no_attachments_still_sends(): void
    {
        EmailAttachment::query()->update(['is_active' => false]);
        $mail = $this->mail();
        $this->assertCount(0, $mail->attachments);
        $html = view($mail->view, $mail->viewData)->render();
        $this->assertStringContainsString('Bring your <strong style="color:#1d2433;">registration number</strong>', $html);
        $this->assertStringNotContainsString('attached to this email', strtolower($html)); // the {attachments} step is left out
    }

    public function test_staff_without_event_settings_cannot_manage(): void
    {
        $role = tap(Role::create(['name' => 'T' . uniqid()]))->syncPermissions(['registrations.view']);
        $u = new User();
        $u->forceFill(['name' => 'Staff', 'email' => uniqid('s') . '@test.local', 'password' => bcrypt('x-secret-1'), 'role' => 'user', 'role_id' => $role->id, 'status' => 'active'])->save();
        $this->actingAs($u)->post('/email-template/attachments', ['title' => 'X'], ['Accept' => 'application/json'])->assertForbidden();
    }
}
