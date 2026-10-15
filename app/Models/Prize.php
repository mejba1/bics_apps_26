<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prize extends Model
{
    use HasFactory;
    protected $filleable = [
        'prize_venue',
        'prize',
        'prize_type',
        'prize_status',
    ];
}
