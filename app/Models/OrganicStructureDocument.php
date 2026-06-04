<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganicStructureDocument extends Model
{
    protected $fillable = [
        'title',
        'original_name',
        'stored_name',
        'path',
        'disk',
        'mime_type',
        'size',
    ];
}
