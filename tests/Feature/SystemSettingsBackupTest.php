<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\DatabaseBackup;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Email server settings, backups, export and import.
 * Imports only ever run against a throw-away database ("bics_import_test"), never the real one.
 */
class SystemSettingsBackupTest extends TestCase
{
    use DatabaseTransactions;

    private array $filesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        MailSettings::forget();
        $this->filesBefore = array_column(DatabaseBackup::list(), 'name');
    }

    protected function tearDown(): void
    {
        // Remove backup files created by these tests
        foreach (DatabaseBackup::list() as $b) {
            if (!in_array($b['name'], $this->filesBefore, true)) File::delete(DatabaseBackup::dir() . '/' . $b['name']);
        }
        MailSettings::forget();
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

    public function test_smtp_settings(): void
    {
        $this->get('/system/mail')->assertRedirect('/login');
        $this->user(['registrations.view'], false);
        $this->get('/system/mail')->assertForbidden();

        $this->user();
        $this->get('/system/mail')->assertOk()->assertSee('Email Server (SMTP)')->assertSee('Using the server settings from the .env file');

        // Validation
        $this->putJson('/system/mail', ['host' => 'http://bad host', 'from_address' => 'nope'])->assertStatus(422)->assertJsonValidationErrors(['host', 'from_address']);
        $this->putJson('/system/mail', ['enabled' => 1, 'host' => ''])->assertStatus(422)->assertJsonValidationErrors('host');

        // Save + switch on: config follows, password stored encrypted
        $this->putJson('/system/mail', [
            'enabled' => 1, 'host' => 'smtp.example.org', 'port' => 465, 'encryption' => 'ssl', 'username' => 'mailer@example.org',
            'password' => 'S3cret-pass', 'from_address' => 'info@example.org', 'from_name' => 'BICS Test', 'reply_to' => 'help@example.org',
        ])->assertOk()->assertJsonPath('effective.source', 'panel')->assertJsonPath('hasPassword', true);
        $stored = DB::table('settings')->where('key', 'mail.password')->value('value');
        $this->assertNotSame('S3cret-pass', $stored);
        $this->assertSame('S3cret-pass', MailSettings::password());
        $this->assertSame('smtp.example.org', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
        $this->assertSame('info@example.org', config('mail.from.address'));
        $this->assertSame('help@example.org', config('mail.reply_to.address'));
        $this->assertSame('array', config('mail.default')); // test/dev mailer is never switched to real SMTP

        // Empty password keeps the saved one; clear_password removes it
        $this->putJson('/system/mail', ['enabled' => 1, 'host' => 'smtp.example.org', 'port' => 465, 'encryption' => 'ssl', 'password' => ''])->assertOk();
        $this->assertSame('S3cret-pass', MailSettings::password());
        $this->putJson('/system/mail', ['enabled' => 0, 'host' => 'smtp.example.org', 'clear_password' => 1])->assertOk()->assertJsonPath('effective.source', 'env');
        $this->assertNull(MailSettings::password());

        // Test email: unreachable server → clear explanation (nothing is sent anywhere)
        $res = $this->postJson('/system/mail/test', ['host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none', 'to' => 'me@test.local'])->assertStatus(500);
        $this->assertStringContainsString('Could not reach the server', $res->json('message'));
        $this->postJson('/system/mail/test', ['host' => '127.0.0.1', 'port' => 1, 'to' => 'not-an-email'])->assertStatus(422);
    }

    public function test_backup_create_download_export_delete(): void
    {
        $this->get('/system/backups')->assertRedirect('/login');
        $this->user(['registrations.view'], false);
        $this->get('/system/backups')->assertForbidden();

        $this->user();
        $this->get('/system/backups')->assertOk()->assertSee('Back up now')->assertSee('Import database')->assertSee('registrations');

        // Back up now (kept on the server)
        $res = $this->postJson('/system/backups')->assertOk();
        $name = $res->json('backups.0.name');
        $this->assertMatchesRegularExpression('/^bics-.+-manual\.sql\.gz$/', $name);
        $sql = gzdecode(file_get_contents(DatabaseBackup::path($name)));
        $this->assertStringContainsString('CREATE TABLE `registrations`', $sql);
        $this->assertStringContainsString('INSERT INTO `registrations`', $sql);
        $this->assertStringEndsWith("-- End of backup\n", $sql);

        // Download / delete; path traversal is refused
        $this->get('/system/backups/' . $name . '/download')->assertOk()->assertDownload($name);
        $this->get('/system/backups/..%2F..%2F.env/download')->assertNotFound();
        $this->get('/system/backups/' . urlencode('../.env') . '/download')->assertNotFound();

        // Export chosen tables as plain .sql and download
        $res = $this->post('/system/backups', ['download' => 1, 'format' => 'sql', 'tables' => ['settings', 'roles']])->assertOk();
        $file = $res->baseResponse->getFile()->getPathname();
        $content = file_get_contents($file);
        $this->assertStringContainsString('CREATE TABLE `settings`', $content);
        $this->assertStringContainsString('CREATE TABLE `roles`', $content);
        $this->assertStringNotContainsString('CREATE TABLE `registrations`', $content);

        $this->deleteJson('/system/backups/' . $name)->assertOk();
        $this->assertNull(DatabaseBackup::path($name));
    }

    public function test_restore_and_import_are_guarded(): void
    {
        $this->user(['system.backup'], false);
        $name = DatabaseBackup::create(['settings'], 'manual');
        $this->get('/system/backups')->assertOk()->assertSee('only a <b>Super Admin</b>', false);
        $this->postJson('/system/backups/' . $name . '/restore', ['password' => 'x-secret-1', 'confirm' => 'REPLACE'])->assertForbidden();

        $this->user();
        $this->postJson('/system/backups/' . $name . '/restore', ['password' => 'wrong', 'confirm' => 'REPLACE'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/system/backups/' . $name . '/restore', ['password' => 'x-secret-1', 'confirm' => 'yes'])->assertStatus(422)->assertJsonValidationErrors('confirm');
        $this->post('/system/backups/import', ['file' => UploadedFile::fake()->create('photo.jpg', 10), 'password' => 'x-secret-1', 'confirm' => 'REPLACE'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
        $this->post('/system/backups/import', ['file' => UploadedFile::fake()->createWithContent('db.sql', 'SELECT 1;'), 'password' => 'wrong', 'confirm' => 'REPLACE'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        // Nothing was imported or uploaded
        $this->assertCount(0, array_filter(DatabaseBackup::list(), fn ($b) => str_contains($b['name'], 'upload')));
    }

    public function test_sql_splitter(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sql');
        file_put_contents($path, "-- comment; with semicolon\n/*!40101 SET NAMES utf8mb4 */;\nCREATE TABLE `a;b` (x text);\n"
            . "INSERT INTO `a;b` VALUES ('it''s; fine'),('back\\'slash; \\\\'),(\"dq;\"); # trailing comment;\n"
            . "INSERT INTO t VALUES ('--not a comment'), ('/* not */');\nUSE other_db;\nSELECT 1");
        $stmts = iterator_to_array(DatabaseBackup::statements($path), false);
        unlink($path);
        $this->assertSame([
            'CREATE TABLE `a;b` (x text)',
            "INSERT INTO `a;b` VALUES ('it''s; fine'),('back\\'slash; \\\\'),(\"dq;\")",
            "INSERT INTO t VALUES ('--not a comment'), ('/* not */')",
            'USE other_db',
            'SELECT 1',
        ], $stmts);
    }

    /** Full round trip into a separate, temporary database. */
    public function test_backup_imports_into_another_database(): void
    {
        $scratch = 'bics_import_test';
        DB::statement("DROP DATABASE IF EXISTS `$scratch`");
        DB::statement("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        config(['database.connections.import_test' => array_merge(config('database.connections.mysql'), ['database' => $scratch])]);
        try {
            $tables = ['registrations', 'settings', 'form_options', 'users'];
            $name = DatabaseBackup::create($tables, 'manual');
            $result = DatabaseBackup::import(DatabaseBackup::path($name), 'import_test');
            $this->assertGreaterThan(8, $result['statements']);
            foreach ($tables as $t) {
                $this->assertSame(DB::table($t)->count(), DB::connection('import_test')->table($t)->count(), $t);
            }
            // Same data, including accents and quotes
            $this->assertEquals(
                DB::table('registrations')->orderBy('id')->pluck('institute', 'id')->all(),
                DB::connection('import_test')->table('registrations')->orderBy('id')->pluck('institute', 'id')->all()
            );
            // Importing twice works (DROP TABLE IF EXISTS first)
            DatabaseBackup::import(DatabaseBackup::path($name), 'import_test');
            $this->assertSame(DB::table('settings')->count(), DB::connection('import_test')->table('settings')->count());
        } finally {
            DB::purge('import_test');
            DB::statement("DROP DATABASE IF EXISTS `$scratch`");
        }
    }
}
