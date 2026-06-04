<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('imported_excel_rows');

        Schema::create('padron_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('numero')->nullable();
            $table->string('cedula')->nullable();
            $table->string('nombre')->nullable();
            $table->string('condicion')->nullable();
            $table->timestamps();

            $table->index('cedula');
            $table->index('nombre');
            $table->index('condicion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('padron_records');
    }
};
