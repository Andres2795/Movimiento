<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovementJoinRequest extends Model
{
    protected $fillable = [
        'full_name',
        'cedula',
        'phone',
        'email',
        'city_or_sector',
        'message',
    ];
}
