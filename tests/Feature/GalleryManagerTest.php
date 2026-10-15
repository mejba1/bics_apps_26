<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Admin > Gallery and the confirmation email. Runs in a transaction; uploaded test files are deleted.
 */
class GalleryManagerTest extends TestCase
{
    use DatabaseTransactions;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            \App\Support\Thumb::forget($f); // resized copies first (their names depend on the original)
            File::delete(base_path($f));
        }
        parent::tearDown();
    }

    private function admin(): self
    {
        $u = new User();
        $u->forceFill(['name' => 'Admin', 'email' => uniqid('g') . '@test.local', 'password' => bcrypt('secret-pass'), 'role' => 'admin'])->save();
        return $this->actingAs($u);
    }

    public function test_manager_is_admin_only(): void
    {
        $this->get('/gallery-manager')->assertRedirect('/login');
        $u = new User();
        $u->forceFill(['name' => 'U', 'email' => uniqid('u') . '@test.local', 'password' => bcrypt('x'), 'role' => 'user'])->save();
        $this->actingAs($u)->get('/gallery-manager')->assertForbidden();
        $this->admin()->get('/gallery-manager')->assertOk()->assertSee('gallery-manager.js')->assertSee('Tanzania 2021');
    }

    public function test_create_album_upload_photos_and_show_on_gallery(): void
    {
        $this->admin();
        $album = $this->postJson('/gallery-manager/albums', ['title' => 'Spain 2026 Test'])->assertOk()->json('album');
        $this->postJson('/gallery-manager/albums', ['title' => 'Spain 2026 Test'])->assertStatus(422)->assertJsonValidationErrors('title');

        // A big "camera" photo is stored at max 2400px; thumbnails are made straight away
        $res = $this->post("/gallery-manager/albums/{$album['id']}/photos", ['file' => UploadedFile::fake()->image('Opening Ceremony.jpg', 4000, 3000)], ['Accept' => 'application/json'])
            ->assertOk()->json('photo');
        $photo = GalleryPhoto::findOrFail($res['id']);
        $this->files[] = $photo->image;
        $this->assertMatchesRegularExpression('#^upload/gallery/' . $album['id'] . '/opening-ceremony-\w{6}\.jpg$#', $photo->image);
        $this->assertSame([2400, 1800], [$photo->width, $photo->height]);
        $this->assertSame([2400, 1800], array_slice(getimagesize(base_path($photo->image)), 0, 2));
        $this->assertStringContainsString('upload/cache/480/', $res['thumb']);

        // Not an image
        $this->post("/gallery-manager/albums/{$album['id']}/photos", ['file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422);

        // Caption, then it shows on the public gallery with its own filter button
        $this->putJson("/gallery-manager/photos/{$photo->id}", ['caption' => 'Opening ceremony'])->assertOk();
        $html = $this->get('/gallery')->assertOk()->getContent();
        $this->assertStringContainsString('Spain 2026 Test <span>1</span>', $html);
        $this->assertStringContainsString('data-caption="Opening ceremony"', $html);

        // Hidden album disappears from the website
        $this->postJson("/gallery-manager/albums/{$album['id']}/toggle")->assertOk()->assertJsonPath('album.is_active', false);
        $this->assertStringNotContainsString('Spain 2026 Test', $this->get('/gallery')->getContent());
    }

    public function test_reorder_move_rename_and_delete(): void
    {
        $this->admin();
        $a = GalleryAlbum::create(['title' => 'A test', 'sort' => 90, 'is_active' => true]);
        $b = GalleryAlbum::create(['title' => 'B test', 'sort' => 91, 'is_active' => true]);
        $p1 = $a->photos()->create(['image' => 'assets/frontend/images/gallery/Bics2023-1.jpg', 'sort' => 1]);
        $p2 = $a->photos()->create(['image' => 'assets/frontend/images/gallery/Bics2023-2.jpg', 'sort' => 2]);

        $this->postJson("/gallery-manager/albums/{$a->id}/photos/reorder", ['ids' => [$p2->id, $p1->id]])->assertOk();
        $this->assertSame([$p2->id, $p1->id], $a->photos()->pluck('id')->all());

        $this->putJson("/gallery-manager/photos/{$p1->id}", ['album_id' => $b->id])->assertOk();
        $this->assertSame($b->id, $p1->fresh()->album_id);

        $this->putJson("/gallery-manager/albums/{$b->id}", ['title' => 'B renamed'])->assertOk()->assertJsonPath('album.title', 'B renamed');
        $this->postJson('/gallery-manager/albums/reorder', ['ids' => [$b->id, $a->id]])->assertOk();
        $this->assertTrue($b->fresh()->sort < $a->fresh()->sort);

        // Deleting never removes the original site photos in assets/
        $this->deleteJson("/gallery-manager/albums/{$a->id}")->assertOk()->assertJsonPath('removed_photos', 1);
        $this->assertDatabaseMissing('gallery_photos', ['id' => $p2->id]);
        $this->assertFileExists(base_path('assets/frontend/images/gallery/Bics2023-2.jpg'));
    }

    public function test_confirmation_email_design_and_preview(): void
    {
        $this->get('/event-settings/email-preview')->assertRedirect('/login');
        $html = $this->admin()->get('/event-settings/email-preview')->assertOk()->getContent();
        $this->assertStringContainsString('484704', $html);
        $this->assertStringContainsString(\App\Support\EventSettings::get('event_name'), $html);
        $this->assertStringContainsString(\App\Support\EventSettings::datesLabel(), $html);
        $this->assertStringContainsString(config('bics.public_url') . '/upload/userprofile/Sample-484704.pdf', $html); // public link, not localhost
        $this->assertStringNotContainsString('localhost', $html);
        $this->assertStringNotContainsString('Palma de Mallorca', $html);

        $mail = (new \App\Notifications\EmailNotification(['pin' => '123456', 'name' => 'Doe', 'title' => 'Dr', 'profession' => 'Nurse', 'qrcode' => 'upload/userprofile/Doe-123456.pdf']))
            ->toMail(new \App\Models\Registration());
        $this->assertStringContainsString('123456', $mail->subject);
        $this->assertCount(1, $mail->rawAttachments + $mail->attachments);
    }
}
