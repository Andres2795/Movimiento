<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_join_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name');
            $table->string('cedula', 20);
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('city_or_sector')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_join_requests');
    }
};
