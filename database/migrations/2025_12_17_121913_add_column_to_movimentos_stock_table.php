<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movimentos_stock', function (Blueprint $table) {
            $table->foreignId('documento_relacionado_id')->nullable()->constrained('documentos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimentos_stock', function (Blueprint $table) {
            //
        });
    }
};
