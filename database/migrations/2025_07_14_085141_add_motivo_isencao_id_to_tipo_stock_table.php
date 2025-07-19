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
        Schema::table('tipo_stock', function (Blueprint $table) {
            $table->integer('motivo_isencao_id');
            $table->foreignId('motivo_isencao_id')->constrained('motivo_isencao')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipo_stock', function (Blueprint $table) {
            $table->dropColumn('motivo_isencao_id');
        });
    }
};
