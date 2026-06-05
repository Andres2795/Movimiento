<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryPhoto extends Model
{
    protected $fillable = [
        'gallery_event_id',
        'original_name',
        'stored_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'sort_order',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(GalleryEvent::class, 'gallery_event_id');
    }
}
