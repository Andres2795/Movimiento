<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadedDocument extends Model
{
    protected $fillable = [
        'original_name',
        'stored_name',
        'path',
        'disk',
        'mime_type',
        'extension',
        'size',
    ];
}
