<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A file attached to every registration confirmation email (invitation card, itinerary…). */
class EmailAttachment extends Model
{
    protected $fillable = ['title', 'path', 'original_name', 'size', 'mime', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'size' => 'integer', 'sort_order' => 'integer'];

    public const MAX_KB = 10240;       // per file
    public const MAX_TOTAL_MB = 20;    // all active files together (Gmail limit is 25 MB incl. encoding)

    public static function active()
    {
        try {
            return static::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                ->filter(fn ($a) => is_file($a->fullPath()))->values();
        } catch (\Throwable $e) {
            return collect(); // table not migrated yet: send the email without attachments
        }
    }

    public function fullPath(): string
    {
        return base_path($this->path);
    }

    public function exists(): bool
    {
        return is_file($this->fullPath());
    }

    /** File name the attendee sees, e.g. "BICS-2026-Itinerary.pdf". */
    public function downloadName(): string
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION)) ?: 'pdf';
        $prefix = Str::slug(\App\Support\EventSettings::get('event_short', 'BICS'));
        $name = str_replace(' ', '-', Str::title(str_replace('-', ' ', Str::slug($this->title)))) ?: 'Attachment';
        return ($prefix ? Str::upper($prefix) . '-' : '') . $name . '.' . $ext;
    }

    public function sizeLabel(): string
    {
        $b = $this->size;
        return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
    }

    public function url(): string
    {
        return asset($this->path);
    }
}
