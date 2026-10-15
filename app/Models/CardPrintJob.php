<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A bulk print / export run (Card printing). Files live in storage/app/print-jobs/{id}/. */
class CardPrintJob extends Model
{
    protected $fillable = ['name', 'event_id', 'template_id', 'settings', 'participant_count', 'page_count', 'status', 'files', 'notes'];
    protected $casts = ['settings' => 'array', 'files' => 'array', 'printed_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(CardPrintJobItem::class, 'job_id')->orderBy('position');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function template()
    {
        return $this->belongsTo(CardTemplate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dir(): string
    {
        return storage_path('app/print-jobs/' . $this->id . '/');
    }

    /** Absolute path of a made file (pdf / zip / csv), if it is still there. */
    public function file(string $kind): ?string
    {
        $name = $this->files[$kind] ?? null;
        return $name && is_file($this->dir() . $name) ? $this->dir() . $name : null;
    }
}
