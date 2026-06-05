<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_documents', function (Blueprint $table): void {
            $table->string('public_name')->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_documents', function (Blueprint $table): void {
            $table->dropColumn('public_name');
        });
    }
};
