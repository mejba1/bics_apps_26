<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One step in an NFC card's history (assigned, encoded, verified, activated, lost, replaced…). */
class NfcCardLog extends Model
{
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];

    public const LABELS = [
        'created' => ['Added to stock', 'bi-box-seam'], 'assigned' => ['Assigned', 'bi-person-plus'], 'reassigned' => ['Reassigned', 'bi-arrow-left-right'],
        'unassigned' => ['Back to stock', 'bi-box-arrow-in-left'], 'encoded' => ['Encoded', 'bi-broadcast'], 'encode_failed' => ['Encoding failed', 'bi-x-octagon'],
        'verified' => ['Verified', 'bi-patch-check'], 'verify_failed' => ['Verification failed', 'bi-exclamation-triangle'], 'activated' => ['Activated', 'bi-check-circle'],
        'deactivated' => ['Deactivated', 'bi-pause-circle'], 'marked_lost' => ['Marked lost', 'bi-question-octagon'], 'marked_damaged' => ['Marked damaged', 'bi-heartbreak'],
        'replaced' => ['Replaced', 'bi-arrow-repeat'], 'replacement' => ['Replacement card', 'bi-arrow-repeat'], 'printed' => ['Printed', 'bi-printer'],
        'unprinted' => ['Print status reset', 'bi-printer'], 'note' => ['Note', 'bi-chat-left-text'],
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
