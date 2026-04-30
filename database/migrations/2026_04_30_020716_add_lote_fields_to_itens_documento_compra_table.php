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
        Schema::table('itens_documento_compra', function (Blueprint $table) {
            // 🔹 Relacionamento com lote (se existir tabela de lotes)
            $table->unsignedBigInteger('lote_id')->nullable()->after('produto_id');

            // 🔹 Informações do lote
            $table->string('lote')->nullable()->after('lote_id');
            $table->string('codigo_lote')->nullable()->after('lote');
            $table->string('lote_codigo_barras')->nullable()->after('codigo_lote');
            $table->date('lote_data_validade')->nullable()->after('lote_codigo_barras');

            // (Opcional) Foreign key — só ativa se já tiver tabela de lotes
            // $table->foreign('lote_id')->references('id')->on('lotes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itens_documento_compra', function (Blueprint $table) {
            // Se tiver foreign key, remove primeiro
            // $table->dropForeign(['lote_id']);

            $table->dropColumn([
                'lote_id',
                'lote',
                'codigo_lote',
                'lote_codigo_barras',
                'lote_data_validade',
            ]);
        });
    }
};
