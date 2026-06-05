<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UploadedDocument extends Model
{
    protected $fillable = [
        'original_name',
        'public_name',
        'stored_name',
        'path',
        'disk',
        'mime_type',
        'extension',
        'size',
    ];

    public function padronRecords(): HasMany
    {
        return $this->hasMany(PadronRecord::class);
    }
}
