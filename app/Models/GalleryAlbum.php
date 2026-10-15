<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** A photo album of the gallery (Admin > Gallery), e.g. "Algeria 2023". */
class GalleryAlbum extends Model
{
    protected $fillable = ['title', 'sort', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function photos()
    {
        return $this->hasMany(GalleryPhoto::class, 'album_id')->orderBy('sort')->orderBy('id');
    }

    /** Visible albums that have photos, with their photos (empty if the tables do not exist yet). */
    public static function forDisplay(): Collection
    {
        try {
            return static::where('is_active', true)->whereHas('photos')->with('photos')->orderBy('sort')->orderBy('id')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
