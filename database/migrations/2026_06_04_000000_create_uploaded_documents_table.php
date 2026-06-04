<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploaded_documents', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->string('extension', 10);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_documents');
    }
};
