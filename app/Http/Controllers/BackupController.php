<?php

namespace App\Http\Controllers;

use App\Support\DatabaseBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin > System > Backup & export.
 * Create / download / delete backups, export chosen tables, download uploaded files as ZIP.
 * Restore and import replace data, so they are Super Admin only, need the password and a typed confirmation,
 * and always take an automatic backup first.
 */
class BackupController extends Controller
{
    public function index(Request $request){
        $stats = DatabaseBackup::tableStats();
        return view('backend.pages.backups', [
            'backups' => DatabaseBackup::list(),
            'tables' => $stats,
            'database' => DB::connection()->getDatabaseName(),
            'totalRows' => array_sum(array_column($stats, 'rows')),
            'totalBytes' => array_sum(array_column($stats, 'bytes')),
            'canRestore' => $request->user()->isSuperAdmin(),
            'maxUploadMb' => $this->maxUploadMb(),
        ]);
    }

    /** Create a backup and keep it on the server (JSON), or download it straight away (download=1). */
    public function create(Request $request){
        $data = $request->validate([
            'tables' => 'nullable|array', 'tables.*' => 'string|max:64',
            'format' => 'nullable|in:gz,sql',
            'download' => 'nullable|boolean',
        ]);
        @set_time_limit(300);
        $tables = !empty($data['tables']) ? $data['tables'] : null;
        $label = $tables ? 'export' : ($request->boolean('download') ? 'export' : 'manual');
        try {
            $name = DatabaseBackup::create($tables, $label, ($data['format'] ?? 'gz') === 'gz');
        } catch (\Throwable $e) {
            Log::error('BICS backup failed', ['error' => $e->getMessage()]);
            return $request->expectsJson()
                ? response()->json(['message' => 'Backup failed: ' . Str::limit($e->getMessage(), 200)], 500)
                : back()->with('error', 'Backup failed: ' . Str::limit($e->getMessage(), 200));
        }
        Log::info('BICS backup created', ['file' => $name, 'by' => $request->user()->email]);
        if ($request->boolean('download')) return response()->download(DatabaseBackup::path($name));
        return response()->json(['success' => true, 'message' => 'Backup created: ' . $name, 'backups' => $this->listJson()]);
    }

    public function download(string $name){
        $path = DatabaseBackup::path($name);
        abort_unless($path, 404);
        return response()->download($path);
    }

    public function destroy(string $name){
        $path = DatabaseBackup::path($name);
        abort_unless($path, 404);
        File::delete($path);
        return response()->json(['success' => true, 'message' => 'Backup deleted.', 'backups' => $this->listJson()]);
    }

    /** Restore a backup from the list (Super Admin). */
    public function restore(Request $request, string $name){
        $path = DatabaseBackup::path($name);
        abort_unless($path, 404);
        if ($error = $this->confirmDangerous($request)) return $error;
        return $this->runImport($request, $path, 'before restore', 'Restored ' . $name);
    }

    /** Import an uploaded .sql / .sql.gz file (Super Admin). */
    public function import(Request $request){
        $request->validate([
            'file' => 'required|file|max:' . ($this->maxUploadMb() * 1024),
        ], ['file.required' => 'Choose an .sql or .sql.gz file.', 'file.max' => 'The file is too large for this server (max ' . $this->maxUploadMb() . ' MB). Upload a .sql.gz (compressed) file.']);
        $file = $request->file('file');
        $original = strtolower($file->getClientOriginalName());
        if (!Str::endsWith($original, ['.sql', '.sql.gz', '.gz'])) {
            return response()->json(['message' => 'Upload an .sql or .sql.gz file.', 'errors' => ['file' => ['Upload an .sql or .sql.gz file.']]], 422);
        }
        if ($error = $this->confirmDangerous($request)) return $error;

        $name = 'bics-upload-' . now()->format('Y-m-d_His') . '-' . (Str::slug(preg_replace('/\.sql(\.gz)?$|\.gz$/', '', $original)) ?: 'import') . (Str::endsWith($original, '.gz') ? '.sql.gz' : '.sql');
        $file->move(DatabaseBackup::dir(), $name);
        $path = DatabaseBackup::dir() . '/' . $name;
        if (!DatabaseBackup::looksLikeSql($path)) {
            File::delete($path);
            return response()->json(['message' => 'This file does not look like an SQL database export.', 'errors' => ['file' => ['This file does not look like an SQL database export.']]], 422);
        }
        return $this->runImport($request, $path, 'before import', 'Imported ' . $file->getClientOriginalName());
    }

    /** All uploaded files (photos, cards, gallery, attachments…) as one ZIP. */
    public function uploadsZip(){
        abort_unless(class_exists(\ZipArchive::class), 500, 'ZIP is not available on this server.');
        @set_time_limit(600);
        $zipPath = DatabaseBackup::dir() . '/bics-uploads-' . now()->format('Y-m-d_His') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $root = base_path('upload');
        foreach (File::allFiles($root) as $f) {
            $rel = str_replace('\\', '/', $f->getRelativePathname());
            if (Str::startsWith($rel, ['tmp', 'cache/'])) continue;
            $zip->addFile($f->getPathname(), 'upload/' . $rel);
            // Photos and PDFs are already compressed: store them as they are (much faster)
            if (preg_match('/\.(jpe?g|png|gif|webp|pdf|zip|gz)$/i', $rel)) $zip->setCompressionName('upload/' . $rel, \ZipArchive::CM_STORE);
        }
        $zip->close();
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    private function runImport(Request $request, string $path, string $safetyLabel, string $what){
        @set_time_limit(900);
        try {
            $safety = DatabaseBackup::create(null, $safetyLabel);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Stopped: the safety backup could not be created (' . Str::limit($e->getMessage(), 150) . '). Nothing was changed.'], 500);
        }
        $userEmail = $request->user()->email;
        try {
            $result = DatabaseBackup::import($path);
        } catch (\Throwable $e) {
            Log::error('BICS import failed', ['file' => basename($path), 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'The import stopped with an error. The database may be partly changed. You can restore the safety backup "' . $safety . '" from the list.',
                'detail' => Str::limit($e->getMessage(), 500),
                'safety' => $safety,
                'backups' => $this->listJson(),
            ], 500);
        }
        DatabaseBackup::prune();
        \App\Support\EventSettings::forget();
        \App\Support\EmailTemplate::forget();
        \App\Support\MailSettings::forget();
        Log::warning('BICS database ' . strtolower($what), ['by' => $userEmail, 'statements' => $result['statements'], 'safety_backup' => $safety]);

        // The imported data may not contain the signed-in account any more
        $stillThere = DB::table('users')->where('email', $userEmail)->exists();
        return response()->json([
            'success' => true,
            'message' => $what . ': ' . number_format($result['statements']) . ' statements run. Safety backup: ' . $safety . '.',
            'relogin' => !$stillThere,
            'backups' => $this->listJson(),
        ]);
    }

    /** Super Admin + current password + typed confirmation. Returns an error response or null. */
    private function confirmDangerous(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only a Super Admin can restore or import a database.'], 403);
        }
        if (!Hash::check((string) $request->input('password'), $request->user()->password)) {
            return response()->json(['message' => 'Your password is not correct.', 'errors' => ['password' => ['Your password is not correct.']]], 422);
        }
        if (strtoupper(trim((string) $request->input('confirm'))) !== 'REPLACE') {
            return response()->json(['message' => 'Type REPLACE to confirm.', 'errors' => ['confirm' => ['Type REPLACE to confirm.']]], 422);
        }
        return null;
    }

    private function listJson(): array
    {
        return array_map(fn ($b) => $b + ['size' => $this->size($b['bytes']), 'date' => static::when($b['time'])], DatabaseBackup::list());
    }

    /** Backup time in the event's timezone, e.g. "12 Oct 2026, 12:57 CEST". */
    public static function when(int $time): string
    {
        return \Carbon\Carbon::createFromTimestamp($time, \App\Support\EventSettings::timezone())->format('j M Y, H:i T');
    }

    private function size(int $b): string
    {
        return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
    }

    private function maxUploadMb(): int
    {
        $toMb = function ($v) {
            $v = trim((string) $v); $n = (float) $v;
            return match (strtolower(substr($v, -1))) { 'g' => $n * 1024, 'k' => $n / 1024, 'm' => $n, default => $n / 1048576 };
        };
        return (int) max(1, min($toMb(ini_get('upload_max_filesize')), $toMb(ini_get('post_max_size')), 200));
    }
}
