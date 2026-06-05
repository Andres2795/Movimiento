<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GalleryEvent extends Model
{
    protected $fillable = [
        'title',
        'description',
        'event_date',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(GalleryPhoto::class)->orderBy('sort_order')->orderBy('id');
    }
}
