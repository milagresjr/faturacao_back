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
        Schema::table('documentos', function (Blueprint $table) {
            $table->enum('estado_documento', [
                'rascunho',
                'emitido',
                'anulado',
                'cancelado',
                'arquivado',
                'transformado'
            ])->nullable()->default('emitido')->after('id');

            $table->enum('estado_pagamento', [
                'nao_pago',
                'parcialmente_pago',
                'pago',
                'reembolsado'
            ])->nullable()->after('estado_documento');

            $table->enum('estado_vencimento', [
                'no_prazo',
                'vencido',
                'em_atraso'
            ])->nullable()->after('estado_pagamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn('estado_documento');
            $table->dropColumn('estado_pagamento');
            $table->dropColumn('estado_vencimento');
        });
    }
};
