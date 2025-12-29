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

        // Remove colunas antigas, se existirem
        if (Schema::hasColumn('movimentos_stock', 'documento_relacionado_id')) {
            $table->dropColumn('documento_relacionado_id');
        }

        if (Schema::hasColumn('movimentos_stock', 'documento_relacionado_type')) {
            $table->dropColumn('documento_relacionado_type');
        }

        // Cria campos morph
        $table->unsignedBigInteger('documento_id')->nullable()->after('origem_movimento');
        $table->string('documento_type')->nullable()->after('documento_id');

        // Índice com nome curto (OBRIGATÓRIO no MySQL)
        $table->index(
            ['documento_id', 'documento_type'],
            'mov_stock_doc_index'
        );
    });
}

public function down(): void
{
    Schema::table('movimentos_stock', function (Blueprint $table) {

        $table->dropIndex('mov_stock_doc_index');
        $table->dropColumn(['documento_id', 'documento_type']);
    });
}

};
