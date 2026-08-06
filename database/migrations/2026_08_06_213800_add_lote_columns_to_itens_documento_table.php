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
            $table->string('lote_id')->nullable()->after('tipo_id');
            $table->string('codigo_lote', 100)->nullable()->after('lote_id');
            $table->date('data_validade')->nullable()->after('codigo_lote');
            $table->json('detalhes_lote')->nullable()->after('data_validade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itens_documento', function (Blueprint $table) {
            $table->dropColumn(['lote_id', 'codigo_lote', 'data_validade', 'detalhes_lote']);
        });
    }
};
