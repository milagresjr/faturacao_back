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
        Schema::table('itens_documento', function (Blueprint $table) {
            $table->foreignId('motivo_isencao_id')->nullable()->constrained('motivo_isencao')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itens_documento', function (Blueprint $table) {
            $table->dropForeign(['motivo_isencao_id']);
            $table->dropColumn('motivo_isencao_id');
        });
    }
};
