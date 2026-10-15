<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardPrintJobItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['job_id', 'participant_id', 'template_id', 'template_version', 'nfc_card_id', 'position', 'warnings', 'printed_at'];
    protected $casts = ['printed_at' => 'datetime'];

    public function participant()
    {
        return $this->belongsTo(Participant::class)->withTrashed();
    }
}
