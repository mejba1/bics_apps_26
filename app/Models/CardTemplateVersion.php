<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A saved version of a card template (restore from the studio). */
class CardTemplateVersion extends Model
{
    public $timestamps = false;
    protected $casts = ['design' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
