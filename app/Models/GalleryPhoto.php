<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A photo in a gallery album. "image" is a path inside assets/ or upload/. */
class GalleryPhoto extends Model
{
    protected $fillable = ['album_id', 'image', 'caption', 'width', 'height', 'sort'];

    public function album()
    {
        return $this->belongsTo(GalleryAlbum::class, 'album_id');
    }

    /** Uploaded through Admin > Gallery (its files may be deleted with it). */
    public function getIsUploadedAttribute(): bool
    {
        return str_starts_with($this->image, 'upload/gallery/');
    }
}
