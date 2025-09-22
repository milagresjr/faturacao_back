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
        Schema::create('documentos_compra', function (Blueprint $table) {
            $table->id();

            // Tipo de documento
            $table->string('tipo_nome');
            $table->string('tipo_sigla');
            $table->string('tipo_cor')->nullable();

            //Numero de fatura
            $table->string('num_fatura');
            $table->string('via');

            // Dados da empresa emissora
            $table->string('empresa_id');
            $table->string('empresa_nome');
            $table->string('empresa_nif')->nullable();
            $table->string('empresa_telefone')->nullable();
            $table->string('empresa_email')->nullable();
            $table->string('empresa_endereco')->nullable();

            // Dados do fornecedor no momento do documento
            $table->string('fornecedor_id');
            $table->string('fornecedor_nome');
            $table->string('fornecedor_nif')->nullable();
            $table->string('fornecedor_telefone')->nullable();
            $table->string('fornecedor_email')->nullable();
            $table->string('fornecedor_endereco')->nullable();

            // Configurações
            $table->date('data_fatura');
            $table->date('data_vencimento')->nullable();
            $table->string('obs_pagamento')->nullable();

            // Descontos e transporte
            $table->decimal('desconto_total', 20, 2)->default(0);
            $table->decimal('taxa_iva', 20, 2)->default(0);
            $table->decimal('valor_fatura', 20, 2)->default(0);
            $table->decimal('retencao', 20, 2)->default(0);

            // Totais
            $table->decimal('total_sem_desconto', 20, 2)->default(0);
            $table->decimal('total_impostos', 20, 2)->default(0);
            $table->decimal('total_geral', 20, 2)->default(0);
            $table->decimal('troco', 20, 2)->default(0);

            $table->string('local_entrega')->nullable();
            $table->date('data_recepcao')->nullable();
            $table->string('observacoes')->nullable();
            $table->boolean('paga')->default(false);
            $table->decimal('valor_pago', 20, 2)->default(0);

            //Hash
            $table->text('hash');

            //Utilizador
            $table->string('utilizador_id');
            $table->string('utilizador');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_compra');
    }
};
