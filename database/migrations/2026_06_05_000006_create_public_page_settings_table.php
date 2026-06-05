<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_page_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('hero_image_path')->nullable();
            $table->string('hero_image_original_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_page_settings');
    }
};
