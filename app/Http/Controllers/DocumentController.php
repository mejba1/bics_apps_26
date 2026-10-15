<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Website › Documents (permission "website.pages"): a PDF at its own short address, e.g. /Itenary-2026.
 *   GET /{slug}       public page with the PDF (viewer + download)
 *   GET /{slug}/file  the PDF itself (inline, or ?download=1)
 * Replacing the file keeps the address, so printed links / QR codes keep working.
 */
class DocumentController extends Controller
{
    public const MAX_KB = 40960;   // 40 MB (also the server's upload limit)
    private const DIR = 'app/documents/';

    /* ---------------- public ---------------- */

    private function find(string $slug): object
    {
        $d = DB::table('documents')->whereRaw('LOWER(slug) = ?', [mb_strtolower($slug)])->first();
        abort_unless($d && $d->active && $d->file && is_file(storage_path(static::DIR . $d->file)), 404);
        return $d;
    }

    public function show(Request $request, string $slug){
        $d = DB::table('documents')->whereRaw('LOWER(slug) = ?', [mb_strtolower($slug)])->first();
        // not a document: a page from Admin › Website Pages (e.g. /speakers), as before
        if (!$d) {
            abort_unless(preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug), 404);
            return app(FrontendController::class)->customPage($slug);
        }
        $d = $this->find($slug);
        if ($d->slug !== $slug) return redirect(url('/' . $d->slug), 301);   // one address only (e.g. /itenary-2026 → /Itenary-2026)
        DB::table('documents')->where('id', $d->id)->increment('views');
        return response()->view('frontend.pages.document', ['d' => $d, 'file' => url('/' . $d->slug . '/file') . '?v=' . strtotime((string) $d->file_updated_at)])
            ->header('X-Robots-Tag', 'noindex');
    }

    public function file(Request $request, string $slug){
        $d = $this->find($slug);
        $download = $request->boolean('download');
        if ($download && !$d->allow_download) abort(403);
        if ($download) DB::table('documents')->where('id', $d->id)->increment('downloads');
        $name = Str::slug($d->title) ?: 'document';
        return response()->file(storage_path(static::DIR . $d->file), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="' . $name . '.pdf"',
            'Cache-Control' => 'public, max-age=300',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /* ---------------- admin ---------------- */

    public function index(){
        $docs = DB::table('documents as d')->leftJoin('users as u', 'u.id', '=', 'd.updated_by')->orderByDesc('d.updated_at')->get(['d.*', 'u.name as by']);
        return view('backend.pages.documents', ['docs' => $docs, 'maxMb' => (int) (static::MAX_KB / 1024)]);
    }

    public function save(Request $request){
        $id = $request->integer('id') ?: null;
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'slug' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'description' => 'nullable|string|max:500',
            'file' => ($id ? 'nullable' : 'required') . '|file|mimes:pdf|mimetypes:application/pdf|max:' . static::MAX_KB,
            'active' => 'nullable|boolean', 'allow_download' => 'nullable|boolean',
        ], [
            'slug.regex' => 'Use letters, numbers, - or _ only (no spaces), e.g. Itenary-2026.',
            'file.required' => 'Choose the PDF file.', 'file.mimes' => 'Only PDF files can be uploaded.', 'file.mimetypes' => 'This file is not a real PDF.',
            'file.max' => 'The PDF must be smaller than ' . (int) (static::MAX_KB / 1024) . ' MB.',
        ]);
        $slug = $data['slug'];
        // the address must be free: not another document, and not a page of the site (e.g. /login, /registration)
        if (DB::table('documents')->whereRaw('LOWER(slug) = ?', [mb_strtolower($slug)])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
            throw ValidationException::withMessages(['slug' => 'Another document already uses /' . $slug . '.']);
        }
        if ($this->takenBySite($slug)) throw ValidationException::withMessages(['slug' => '/' . $slug . ' is already a page of the website. Choose another name.']);

        $doc = $id ? DB::table('documents')->where('id', $id)->first() : null;
        if ($id && !$doc) abort(404);
        $row = ['title' => trim(strip_tags($data['title'])), 'slug' => $slug, 'description' => isset($data['description']) ? trim(strip_tags($data['description'])) : null,
            'active' => $request->boolean('active', true), 'allow_download' => $request->boolean('allow_download', true), 'updated_by' => $request->user()->id, 'updated_at' => now()];
        if ($request->hasFile('file')) {
            $f = $request->file('file');
            $head = (string) file_get_contents($f->getRealPath(), false, null, 0, 5);
            if ($head !== '%PDF-') throw ValidationException::withMessages(['file' => 'This file is not a real PDF.']);
            $dir = storage_path(static::DIR);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $name = Str::slug($slug) . '-' . date('YmdHis') . '-' . Str::random(6) . '.pdf';
            $f->move($dir, $name);
            if ($doc && $doc->file) @unlink($dir . $doc->file);   // the old version
            $row += ['file' => $name, 'original_name' => mb_substr($f->getClientOriginalName(), 0, 250), 'size' => filesize($dir . $name), 'file_updated_at' => now()];
        }
        if ($doc) DB::table('documents')->where('id', $doc->id)->update($row);
        else $id = DB::table('documents')->insertGetId($row + ['created_by' => $request->user()->id, 'created_at' => now()]);
        return response()->json(['success' => true, 'id' => $id, 'url' => url('/' . $slug),
            'message' => ($doc ? 'Saved' : 'Uploaded') . ' — the PDF is at ' . preg_replace('#^https?://#', '', url('/' . $slug)) . ($row['active'] ? '' : ' (hidden until you switch it on)')]);
    }

    public function toggle(Request $request, int $id){
        $n = DB::table('documents')->where('id', $id)->update(['active' => $request->boolean('active'), 'updated_by' => $request->user()->id, 'updated_at' => now()]);
        abort_unless($n || DB::table('documents')->where('id', $id)->exists(), 404);
        return response()->json(['success' => true, 'message' => $request->boolean('active') ? 'The link is live.' : 'The link is switched off (visitors see "not found").']);
    }

    public function destroy(int $id){
        $d = DB::table('documents')->where('id', $id)->first();
        abort_unless($d, 404);
        if ($d->file) @unlink(storage_path(static::DIR . $d->file));
        DB::table('documents')->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => '/' . $d->slug . ' deleted.']);
    }

    /** QR code of the link (PNG / SVG) for posters and slides. */
    public function qr(int $id, string $format){
        $d = DB::table('documents')->where('id', $id)->first();
        abort_unless($d, 404);
        $data = \App\Support\QrImage::make(url('/' . $d->slug), $format, ['size' => 1200, 'margin' => 2]);
        return response($data, 200, ['Content-Type' => \App\Support\QrImage::FORMATS[$format], 'Content-Disposition' => 'attachment; filename="' . Str::slug($d->slug) . '-qr.' . $format . '"']);
    }

    /** Is /{slug} already answered by another route of the site? */
    private function takenBySite(string $slug): bool
    {
        foreach (['GET', 'POST'] as $method) {
            try {
                $route = app('router')->getRoutes()->match(Request::create('/' . $slug, $method));
                if ($route->getName() !== 'document.show') return true;
            } catch (\Throwable $e) {
                // not found / method not allowed for this method: free
            }
        }
        // a page made in Admin › Website Pages
        if (\App\Models\Page::whereRaw('LOWER(slug) = ?', [mb_strtolower($slug)])->exists()) return true;
        // real files and folders in the site root (upload/, assets/ …)
        return file_exists(base_path($slug)) || file_exists(public_path($slug));
    }
}
