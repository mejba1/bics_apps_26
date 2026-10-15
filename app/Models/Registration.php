<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Registration extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
    'event_id',
    'venue',
    'title',
    'trackingNo',
    'onbehalf',
    'country',
    'firstname',
    'lastname',
    'birthday',
    'gender',
    'email',
    'phone',
    'profession',
    'specialty',
    'pharmacist',
    'designation',
    'institute',
    'institute_address',
    'institute_phone',
    'identification_type',
    'identification_no',
    'image',
    'qr_code',
    'qr_img',
    'qr_url',
    'verify_at',
    'verify_status',
    'email_notification',
    'gift',
    'qr_img_path',
    'status',
];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
