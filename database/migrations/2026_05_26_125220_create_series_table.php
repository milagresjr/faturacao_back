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
        Schema::create('series', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id');

            $table->string('nome');
            $table->string('prefixo', 20);
            $table->string('ano', 4)->default(date('Y'));

            $table->enum('tipo_documento', [
                'factura',
                'factura_recibo',
                'fatura_global',
                'recibo',
                'proforma',
                'orcamento',
                'encomenda',
                'nota_credito',
                'nota_debito',
                'guia_remessa',
                'guia_transporte',
                'entrada',
                'saida',
                'entrada_inventario',
                'saida_inventario',
                'nota_quebra',
                'transferencia',
            ]);

            $table->integer('sequencia_atual')->default(0);

            $table->boolean('padrao')->default(false);
            $table->boolean('ativo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'tipo_documento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
