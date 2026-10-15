<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendee extends Model
{
    use HasFactory;
    protected $filleable = [
        'venue',
        'trackingNo',
        'onbehalf',
        'qr_code',
        'qr_url',
        'qr_img_path',
        'email_notification',
        'ip',
        'country',
        'city',
        'apply_date',
        'gift',
        'verify_at',
        'verify_status',
        'winner_status',
        'status', 
    ];
}
