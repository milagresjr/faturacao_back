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
            // Colunas para controlo de validade
            $table->string('lote_id')->nullable()->after('produto_id');
            $table->string('codigo_lote', 100)->nullable()->after('lote_id');
            $table->date('data_validade_lote')->nullable()->after('codigo_lote');
            $table->json('detalhes_lote')->nullable()->after('data_validade_lote');

            // Índices para performance
            $table->index('lote_id');
            $table->index('codigo_lote');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimentos_stock', function (Blueprint $table) {
            $table->dropColumn(['lote_id', 'codigo_lote', 'data_validade_lote', 'detalhes_lote']);
        });
    }
};
