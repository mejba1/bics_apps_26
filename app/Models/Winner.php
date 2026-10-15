<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Winner extends Model
{
    use HasFactory;
    protected $filleable = [
        'register_id',
        'winners_venue',
        'winners_name',
        'winners_designation',
        'winners_country',
        'winners_number',
        'prize',
        'prize_type',
    ];
}
