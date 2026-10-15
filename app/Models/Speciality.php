<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Speciality extends Model
{
    use HasFactory;
    protected $filleable = [
        'id',
        'profession_id',
        'speciality',
    ];
}
