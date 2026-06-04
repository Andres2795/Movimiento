<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PadronRecord extends Model
{
    protected $fillable = [
        'uploaded_document_id',
        'numero',
        'cedula',
        'nombre',
        'condicion',
    ];

    public function uploadedDocument(): BelongsTo
    {
        return $this->belongsTo(UploadedDocument::class);
    }
}
