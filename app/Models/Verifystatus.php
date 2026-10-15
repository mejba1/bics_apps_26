<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Verifystatus extends Model
{
    use HasApiTokens, HasFactory, Notifiable;
   
    protected $filleable = [
        'qr_code',
        'verify_status',
        'verify_ip',
        'verify_country',
        'verify_city',
        'verify_date',     
    ];
}
