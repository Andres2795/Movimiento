<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicPageSetting extends Model
{
    protected $fillable = [
        'hero_image_path',
        'hero_image_original_name',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}
