<?php

namespace App\Http\Controllers;

use App\Models\EmailAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Admin > Email Template > Confirmation email attachments (AJAX).
 * Every active file is attached to the registration confirmation email.
 */
class EmailAttachmentController extends Controller
{
    private const DIR = 'upload/email-attachments';

    public function store(Request $request){
        $request->validate([
            'title' => 'required|max:120',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:' . EmailAttachment::MAX_KB,
        ], $this->messages());

        if ($error = $this->tooBig($request->file('file')->getSize())) {
            return response()->json(['message' => $error, 'errors' => ['file' => [$error]]], 422);
        }

        $a = new EmailAttachment(['title' => $request->title, 'is_active' => true,
            'sort_order' => (int) EmailAttachment::max('sort_order') + 1]);
        $this->putFile($a, $request->file('file'));
        $a->save();

        return response()->json(['success' => true, 'message' => '"' . $a->title . '" will be attached to confirmation emails.', 'item' => $this->present($a), 'total' => $this->total()]);
    }

    /** Rename, switch on/off, or replace the file. */
    public function update(Request $request, EmailAttachment $attachment){
        $request->validate([
            'title' => 'sometimes|required|max:120',
            'is_active' => 'sometimes|boolean',
            'file' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:' . EmailAttachment::MAX_KB,
        ], $this->messages());

        $newSize = $request->hasFile('file') ? $request->file('file')->getSize() : $attachment->size;
        $willBeActive = $request->has('is_active') ? $request->boolean('is_active') : $attachment->is_active;
        if ($willBeActive && ($error = $this->tooBig($newSize, $attachment->id))) {
            return response()->json(['message' => $error, 'errors' => ['file' => [$error]]], 422);
        }

        if ($request->has('title')) $attachment->title = $request->title;
        if ($request->has('is_active')) $attachment->is_active = $request->boolean('is_active');
        if ($request->hasFile('file')) {
            $old = $attachment->fullPath();
            $this->putFile($attachment, $request->file('file'));
            if ($old !== $attachment->fullPath()) File::delete($old);
        }
        $attachment->save();

        return response()->json(['success' => true, 'message' => 'Saved.', 'item' => $this->present($attachment), 'total' => $this->total()]);
    }

    public function destroy(EmailAttachment $attachment){
        File::delete($attachment->fullPath());
        $attachment->delete();
        return response()->json(['success' => true, 'message' => 'Attachment removed.', 'total' => $this->total()]);
    }

    public function order(Request $request){
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        foreach (array_values($ids) as $i => $id) {
            EmailAttachment::where('id', $id)->update(['sort_order' => $i + 1]);
        }
        return response()->json(['success' => true]);
    }

    /** Admin list data (also used by the Event settings page). */
    public static function present(EmailAttachment $a): array
    {
        return [
            'id' => $a->id, 'title' => $a->title, 'is_active' => $a->is_active, 'url' => $a->url(),
            'original_name' => $a->original_name, 'download_name' => $a->downloadName(),
            'size' => $a->sizeLabel(), 'bytes' => $a->size, 'missing' => !$a->exists(),
            'type' => Str::startsWith($a->mime, 'image/') ? 'image' : 'pdf',
        ];
    }

    private function putFile(EmailAttachment $a, UploadedFile $file): void
    {
        File::ensureDirectoryExists(base_path(self::DIR));
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'pdf');
        $name = (Str::slug($a->title) ?: 'attachment') . '-' . Str::lower(Str::random(6)) . '.' . $ext;
        $file->move(base_path(self::DIR), $name);
        $a->path = self::DIR . '/' . $name;
        $a->original_name = Str::limit($file->getClientOriginalName(), 250, '');
        $a->size = filesize(base_path($a->path));
        $a->mime = $ext === 'pdf' ? 'application/pdf' : ($ext === 'png' ? 'image/png' : 'image/jpeg');
    }

    /** Keep the email under the mail provider's size limit. */
    private function tooBig(int $bytes, ?int $exceptId = null): ?string
    {
        $others = EmailAttachment::where('is_active', true)->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->sum('size');
        if ($others + $bytes > EmailAttachment::MAX_TOTAL_MB * 1048576) {
            return 'All attachments together must stay under ' . EmailAttachment::MAX_TOTAL_MB . ' MB, or the email may not be delivered. Switch off or compress another file first.';
        }
        return null;
    }

    private function total(): string
    {
        $b = EmailAttachment::where('is_active', true)->sum('size');
        return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB';
    }

    private function messages(): array
    {
        return [
            'file.mimes' => 'Upload a PDF, JPG or PNG file.',
            'file.max' => 'The file is too large (max ' . (EmailAttachment::MAX_KB / 1024) . ' MB). Please compress it first.',
            'file.required' => 'Choose a file to attach.',
            'title.required' => 'Give the attachment a name, e.g. "Itinerary".',
        ];
    }
}
